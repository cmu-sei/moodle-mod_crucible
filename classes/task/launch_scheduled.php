<?php
namespace mod_crucible\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Launches scheduled Crucible labs from Moodle cron.
 *
 * @package    mod_crucible
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class launch_scheduled extends \core\task\adhoc_task {

    public function get_component(): string {
        return 'mod_crucible';
    }

    public function execute() {
        global $CFG;

        require_once($CFG->dirroot . '/mod/crucible/locallib.php');

        $custom = $this->get_custom_data();
        $scheduleids = array_filter(array_map('intval', (array)($custom->scheduleids ?? [])));
        if (!$scheduleids) {
            mtrace('launch_scheduled: no schedule ids supplied');
            return;
        }

        $auth = setup_system(false);
        if (!$auth) {
            throw new \moodle_exception('systemauthfailed', 'mod_crucible');
        }

        foreach ($scheduleids as $scheduleid) {
            $this->launch_one($auth, $scheduleid);
        }
    }

    private function launch_one($auth, int $scheduleid): void {
        global $DB;

        $schedule = $DB->get_record('crucible_scheduled_launches', ['id' => $scheduleid]);
        if (!$schedule || $schedule->status !== 'scheduled') {
            mtrace("launch_scheduled: schedule $scheduleid is not pending");
            return;
        }

        if ($schedule->scheduledfor > time()) {
            $task = new self();
            $task->set_custom_data((object)['scheduleids' => [$scheduleid]]);
            $task->set_component('mod_crucible');
            $task->set_next_run_time((int)$schedule->scheduledfor);
            \core\task\manager::queue_adhoc_task($task);
            mtrace("launch_scheduled: schedule $scheduleid is not due; requeued");
            return;
        }

        $crucible = $DB->get_record('crucible', ['id' => $schedule->crucibleid], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['id' => $schedule->userid], '*', MUST_EXIST);

        $activeattempt = $DB->get_record('crucible_attempts', [
            'crucibleid' => $crucible->id,
            'userid' => $user->id,
            'state' => \mod_crucible\crucible_attempt::INPROGRESS,
        ], '*', IGNORE_MULTIPLE);
        if ($activeattempt) {
            $schedule->status = 'launched';
            $schedule->attemptid = $activeattempt->id;
            $schedule->timemodified = time();
            $DB->update_record('crucible_scheduled_launches', $schedule);
            mtrace("launch_scheduled: user {$user->id} already has active attempt {$activeattempt->id}");
            return;
        }

        $alloyuserid = $this->get_alloy_userid($user);
        if (!$alloyuserid) {
            $schedule->status = 'failed';
            $schedule->timemodified = time();
            $DB->update_record('crucible_scheduled_launches', $schedule);
            mtrace("launch_scheduled: user {$user->id} has no Alloy user id in Moodle user.idnumber");
            return;
        }

        $eventtemplate = get_eventtemplate($auth, $crucible->eventtemplateid);
        $event = start_event_for_user($auth, $crucible->eventtemplateid, $alloyuserid, fullname($user));
        if (!$event || empty($event->id)) {
            $schedule->status = 'failed';
            $schedule->timemodified = time();
            $DB->update_record('crucible_scheduled_launches', $schedule);
            mtrace("launch_scheduled: failed to create Alloy event for user {$user->id}");
            return;
        }

        $attemptid = $this->create_attempt($crucible, $user, $event, $eventtemplate);

        $schedule->status = 'launched';
        $schedule->attemptid = $attemptid;
        $schedule->timemodified = time();
        $DB->update_record('crucible_scheduled_launches', $schedule);

        mtrace("launch_scheduled: launched schedule $scheduleid as event {$event->id}");
    }

    private function get_alloy_userid(\stdClass $user): ?string {
        $idnumber = trim((string)($user->idnumber ?? ''));
        if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $idnumber)) {
            return $idnumber;
        }
        return null;
    }

    private function create_attempt(\stdClass $crucible, \stdClass $user, \stdClass $event, ?\stdClass $eventtemplate): int {
        global $DB;

        $now = time();
        $attempt = (object)[
            'crucibleid' => $crucible->id,
            'userid' => $user->id,
            'eventid' => $event->id,
            'scenarioid' => $event->scenarioId ?? null,
            'state' => \mod_crucible\crucible_attempt::INPROGRESS,
            'tasks' => '',
            'score' => 0,
            'endtime' => $this->get_endtime($event, $eventtemplate),
            'timestart' => $now,
            'timefinish' => null,
            'timemodified' => $now,
        ];

        $attemptid = $DB->insert_record('crucible_attempts', $attempt);
        $this->create_task_result_rows($crucible->id, $attemptid);

        return (int)$attemptid;
    }

    private function get_endtime(\stdClass $event, ?\stdClass $eventtemplate): int {
        if (!empty($event->expirationDate)) {
            return strpos($event->expirationDate, 'Z') !== false
                ? strtotime($event->expirationDate)
                : strtotime($event->expirationDate . 'Z');
        }

        $durationhours = 8;
        if (!empty($eventtemplate->durationHours)) {
            $durationhours = (float)$eventtemplate->durationHours;
        }

        return time() + (int)round($durationhours * 3600);
    }

    private function create_task_result_rows(int $crucibleid, int $attemptid): void {
        global $DB;

        $tasks = $DB->get_records('crucible_tasks', ['crucibleid' => $crucibleid]);
        foreach ($tasks as $task) {
            $DB->insert_record('crucible_task_results', (object)[
                'taskid' => $task->id,
                'dispatchtaskid' => $task->dispatchtaskid,
                'attemptid' => $attemptid,
                'vmname' => 'SUMMARY',
                'timemodified' => time(),
            ]);
        }
    }
}
