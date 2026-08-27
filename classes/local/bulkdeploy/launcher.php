<?php
namespace mod_crucible\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/crucible/locallib.php');

/**
 * Drives one batch of bulk-deploy work for Crucible: launch events sequentially,
 * poll until each is ready, then move to next user.
 *
 * Unlike TopoMojo, Crucible deployments are ALWAYS sequential (batchsize=1)
 * due to Terraform provisioning complexity.
 */
class launcher {
    public function __construct(
        private job_repository $repo,
        private int $pollintervalsec = 10,   // sleep between poll cycles (default 10s)
        private int $waitceilingsec = 600    // max wait time per user (default 600s = 10min)
    ) {}

    /**
     * @param int $jobid
     * @param array $batch each entry: ['rowid' => int, 'user' => stdClass]
     * @param \stdClass $crucible activity record
     */
    public function run_batch(int $jobid, array $batch, \stdClass $crucible): void {
        // For Crucible, batch is always sequential (one user at a time)
        foreach ($batch as $entry) {
            $this->process_user($entry, $crucible);
        }
    }

    /**
     * Process a single user: launch an event if needed, then wait for ready.
     */
    private function process_user(array $entry, \stdClass $crucible): void {
        $rowid = $entry['rowid'];
        $user = $entry['user'];

        // Always use the current status: a resumed task must reconcile launched
        // rows rather than silently skipping them.
        $statuses = $this->repo->get_user_statuses([$rowid]);
        $status = $statuses[$rowid] ?? null;
        if ($status !== user_status::PENDING && $status !== user_status::LAUNCHED) {
            return; // Externally cancelled or already terminal.
        }

        $auth = setup_system();
        if (!$auth) {
            $this->repo->set_user_status($rowid, user_status::FAILED, 'Could not initialize API client');
            return;
        }

        $waitforready = $status === user_status::PENDING;
        if ($waitforready) {
            // Get the user's Alloy GUID before launching so we do not create an event
            // that cannot be assigned to the target student.
            $useralloyguid = get_user_alloy_guid($user->id);
            if (!$useralloyguid) {
                $this->repo->set_user_status(
                    $rowid,
                    user_status::FAILED,
                    'User does not have Alloy GUID (not OAuth2 user)',
                    ''
                );
                return;
            }

            $userdisplayname = \fullname($user) ?: $user->username;
            try {
                $eventid = start_event($auth, $crucible->eventtemplateid, $useralloyguid, $userdisplayname);
                if (!$eventid) {
                    $this->repo->set_user_status($rowid, user_status::FAILED, 'Failed to start event (no eventid returned)', '');
                    return;
                }

                debugging("Event $eventid created for user {$user->username}", DEBUG_DEVELOPER);
            } catch (\Throwable $e) {
                $this->repo->set_user_status(
                    $rowid,
                    user_status::FAILED,
                    'Exception starting event: ' . $e->getMessage(),
                    ''
                );
                return;
            }

            // Persist the event ID before polling so a later task execution can
            // resume this row without starting a duplicate Alloy event.
            $this->repo->set_user_status($rowid, user_status::LAUNCHED, '', $eventid);
            $timestarted = time();
        } else {
            $eventid = trim((string)($entry['eventid'] ?? ''));
            if ($eventid === '') {
                $this->repo->set_user_status(
                    $rowid,
                    user_status::FAILED,
                    'Launched deployment is missing its Alloy event ID'
                );
                return;
            }
            $timestarted = (int)($entry['timestarted'] ?? 0);
        }

        $this->wait_for_event($rowid, $user, $crucible, $auth, $eventid, $timestarted, $waitforready);
    }

    /**
     * Polls a launched Alloy event until it becomes active or reaches a terminal state.
     */
    private function wait_for_event(
        int $rowid,
        \stdClass $user,
        \stdClass $crucible,
        \core\oauth2\client $auth,
        string $eventid,
        int $timestarted,
        bool $waitforready
    ): void {
        $start = $timestarted > 0 ? $timestarted : time();
        while (true) {
            // Check if externally cancelled during wait
            $statuses = $this->repo->get_user_statuses([$rowid]);
            if (($statuses[$rowid] ?? null) !== user_status::LAUNCHED) {
                return; // Externally mutated, stop waiting
            }

            // Poll event status
            $event = null;
            try {
                $event = get_event($auth, $eventid);
                if (!$event) {
                    debugging("No event response received for $eventid", DEBUG_DEVELOPER);
                } else {
                    // Check if event failed/ended
                    $status = strtolower($event->status ?? '');
                    if ($status === 'ended' || $status === 'failed' || $status === 'error') {
                        $errmsg = $event->errorMessage ?? 'Event ended without becoming active';
                        $this->repo->set_user_status($rowid, user_status::FAILED, "Event deployment failed: $errmsg");
                        return;
                    }

                    // Check if event is ready (has status or isActive flag)
                    $isready = $status === 'active';
                    if (!$isready && isset($event->isActive)) {
                        $isready = (bool)$event->isActive;
                    }

                    if ($isready) {
                        // Create the attempt before marking the row ready so a
                        // task interruption can be retried without losing it.
                        $this->create_attempt_for_user($user->id, $crucible, $event);
                        $this->repo->set_user_status($rowid, user_status::READY);
                        return;
                    }
                }
            } catch (\Throwable $e) {
                // Continue polling on transient errors
                debugging("Error polling event $eventid: " . $e->getMessage(), DEBUG_DEVELOPER);
            }

            // A reconciliation task only checks the current event state once, so
            // an earlier wait ceiling never prevents a later successful event
            // from creating its Moodle attempt.
            if (!$waitforready) {
                return;
            }

            // The initial task stops blocking after the configured limit, but
            // deliberately leaves the row launched for a reconciliation task.
            // Alloy terminal states above remain the only failure condition.
            if ((time() - $start) >= $this->waitceilingsec) {
                debugging(
                    "Bulk deployment wait timeout reached for event $eventid; leaving it launched for reconciliation",
                    DEBUG_DEVELOPER
                );
                return;
            }

            // Sleep before next poll
            $this->sleep_seconds($this->pollintervalsec);
        }
    }

    /**
     * Returns an Alloy API client with a bounded connection and request duration.
     */
    private function get_api_client(): \core\oauth2\client|false {
        $auth = setup_system();
        if (!$auth) {
            return false;
        }

        return $auth;
    }

    protected function sleep_seconds(int $seconds): void {
        sleep($seconds);
    }

    /**
     * Creates an attempt record for a successfully deployed event.
     * @param int $userid
     * @param \stdClass $crucible activity record
     * @param \stdClass $event decoded event response from Alloy API
     */
    private function create_attempt_for_user(int $userid, \stdClass $crucible, \stdClass $event): void {
        global $DB;

        // Check if user already has an open attempt for this activity
        $existing = $DB->get_record('crucible_attempts', [
            'crucibleid' => $crucible->id,
            'userid' => $userid,
            'state' => 'inprogress'
        ]);

        if ($existing) {
            // User already has an open attempt, don't create duplicate
            return;
        }

        $attempt = new \stdClass();
        $attempt->crucibleid = $crucible->id;
        $attempt->userid = $userid;
        $attempt->eventid = $event->id;
        $attempt->state = 'inprogress';
        $attempt->timestart = time();
        $attempt->timemodified = time();
        $attempt->timefinish = null;

        // Parse expirationDate if present
        if (!empty($event->expirationDate)) {
            $attempt->endtime = is_numeric($event->expirationDate)
                ? (int) $event->expirationDate
                : strtotime($event->expirationDate);
        } else {
            // Default to 2 hours from now
            $attempt->endtime = time() + 7200;
        }

        $attempt->score = 0;
        $attempt->tasks = null;
        $attempt->scenarioid = null;

        $DB->insert_record('crucible_attempts', $attempt);
    }
}
