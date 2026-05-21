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
     * Process a single user: launch event, wait for ready, create attempt.
     */
    private function process_user(array $entry, \stdClass $crucible): void {
        $rowid = $entry['rowid'];
        $user = $entry['user'];

        // Check if externally cancelled before starting
        $statuses = $this->repo->get_user_statuses([$rowid]);
        if (($statuses[$rowid] ?? null) !== user_status::PENDING) {
            return; // Externally mutated, skip
        }

        // Get system auth client
        $auth = setup_system();
        if (!$auth) {
            $this->repo->set_user_status($rowid, user_status::FAILED, 'Could not initialize API client');
            return;
        }

        // Launch event
        try {
            $eventid = start_event($auth, $crucible->eventtemplateid);
            if (!$eventid) {
                $this->repo->set_user_status($rowid, user_status::FAILED, 'Failed to start event (no eventid returned)');
                return;
            }
        } catch (\Exception $e) {
            $this->repo->set_user_status($rowid, user_status::FAILED, 'Exception starting event: ' . $e->getMessage());
            return;
        }

        // Mark as launched
        $this->repo->set_user_status($rowid, user_status::LAUNCHED, null, $eventid);

        // Wait phase: poll until event is ready
        $start = time();
        while (true) {
            // Check if externally cancelled during wait
            $statuses = $this->repo->get_user_statuses([$rowid]);
            if (($statuses[$rowid] ?? null) !== user_status::LAUNCHED) {
                return; // Externally mutated, stop waiting
            }

            // Poll event status
            try {
                $event = get_event($auth, $eventid);
                if (!$event) {
                    // Event not found or error
                    $this->sleep_seconds($this->pollintervalsec);
                    if ((time() - $start) >= $this->waitceilingsec) {
                        $this->repo->set_user_status($rowid, user_status::FAILED, 'Timeout waiting for event (event not found)');
                        return;
                    }
                    continue;
                }

                // Check if event is ready (has status or isActive flag)
                $isReady = !empty($event->status) && strtolower($event->status) === 'active';
                if (!$isReady && isset($event->isActive)) {
                    $isReady = (bool) $event->isActive;
                }

                if ($isReady) {
                    // Event is ready, mark as ready and create attempt
                    $this->repo->set_user_status($rowid, user_status::READY);
                    $this->create_attempt_for_user($user->id, $crucible, $event);
                    return;
                }

            } catch (\Exception $e) {
                // Continue polling on transient errors
                debugging("Error polling event $eventid: " . $e->getMessage(), DEBUG_DEVELOPER);
            }

            // Check timeout
            if ((time() - $start) >= $this->waitceilingsec) {
                $laststatus = isset($event) ? ($event->status ?? 'unknown') : 'no-response';
                $this->repo->set_user_status($rowid, user_status::FAILED, "Timeout waiting for event (last status: $laststatus)");
                return;
            }

            // Sleep before next poll
            $this->sleep_seconds($this->pollintervalsec);
        }
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
