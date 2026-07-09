<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/crucible/locallib.php');

$action = required_param('action', PARAM_ALPHANUMEXT);
$cmid = required_param('id', PARAM_INT);
require_sesskey();

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'crucible');
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
if (!has_any_capability(['mod/crucible:managelabs', 'mod/crucible:manage'], $context)) {
    require_capability('mod/crucible:manage', $context);
}

$crucible = $DB->get_record('crucible', ['id' => $cm->instance], '*', MUST_EXIST);
$returnurl = new moodle_url('/mod/crucible/manage.php', ['id' => $cmid]);
$PAGE->set_url(new moodle_url('/mod/crucible/manage_action.php', ['id' => $cmid, 'action' => $action]));
$PAGE->set_context($context);

$userids = required_param('userids', PARAM_TEXT);
$userids = array_filter(array_map('intval', explode(',', $userids)));

if (!$userids) {
    \core\notification::error(get_string('nousersselected', 'mod_crucible'));
    redirect($returnurl);
}

switch ($action) {
    case 'schedule_selected':
        $scheduledfor = required_param('scheduledfor', PARAM_INT);
        $scheduledtimezone = optional_param('scheduledtimezone', '', PARAM_RAW_TRIMMED);
        if (strlen($scheduledtimezone) > 64 || !preg_match('/^[A-Za-z0-9_+\\-\\/]+$/', $scheduledtimezone)) {
            $scheduledtimezone = '';
        }

        if ($scheduledfor <= time()) {
            \core\notification::error(get_string('schedulepast', 'mod_crucible'));
            redirect($returnurl);
        }

        $scheduled = 0;
        $scheduleids = [];
        foreach ($userids as $userid) {
            $activeattempt = $DB->get_record('crucible_attempts', [
                'crucibleid' => $crucible->id,
                'userid' => $userid,
                'state' => \mod_crucible\crucible_attempt::INPROGRESS,
            ], 'id', IGNORE_MULTIPLE);
            if ($activeattempt) {
                continue;
            }

            $existing = $DB->get_record('crucible_scheduled_launches', [
                'crucibleid' => $crucible->id,
                'userid' => $userid,
                'status' => 'scheduled',
            ], '*', IGNORE_MULTIPLE);

            if ($existing) {
                $existing->scheduledfor = $scheduledfor;
                $existing->scheduledtimezone = $scheduledtimezone;
                $existing->status = 'scheduled';
                $existing->attemptid = null;
                $existing->timemodified = time();
                $DB->update_record('crucible_scheduled_launches', $existing);
                $scheduleids[] = (int)$existing->id;
            } else {
                $record = new stdClass();
                $record->crucibleid = $crucible->id;
                $record->userid = $userid;
                $record->scheduledfor = $scheduledfor;
                $record->scheduledtimezone = $scheduledtimezone;
                $record->status = 'scheduled';
                $record->attemptid = null;
                $record->timecreated = time();
                $record->timemodified = time();
                $scheduleids[] = (int)$DB->insert_record('crucible_scheduled_launches', $record);
            }
            $scheduled++;
        }

        if ($scheduleids) {
            $task = new \mod_crucible\task\launch_scheduled();
            $task->set_custom_data((object)['scheduleids' => $scheduleids]);
            $task->set_component('mod_crucible');
            $task->set_next_run_time($scheduledfor);
            \core\task\manager::queue_adhoc_task($task);
        }

        \core\notification::success(get_string('labsscheduled', 'mod_crucible', $scheduled));
        redirect($returnurl);
        break;

    case 'end_selected':
        $auth = setup_management_auth();
        if (!$auth) {
            \core\notification::error(get_string('systemauthfailed', 'mod_crucible'));
            redirect($returnurl);
        }

        $ended = 0;

        foreach ($userids as $userid) {
            $attempt = $DB->get_record('crucible_attempts', [
                'crucibleid' => $crucible->id,
                'userid' => $userid,
                'state' => \mod_crucible\crucible_attempt::INPROGRESS,
            ], '*', IGNORE_MULTIPLE);

            if (!$attempt || empty($attempt->eventid)) {
                continue;
            }

            stop_event($auth, $attempt->eventid);
            $attempt->state = \mod_crucible\crucible_attempt::FINISHED;
            $attempt->timefinish = time();
            $attempt->timemodified = time();
            $DB->update_record('crucible_attempts', $attempt);
            $ended++;
        }

        \core\notification::success(get_string('labsended', 'mod_crucible', $ended));
        redirect($returnurl);
        break;

    case 'extend_selected':
        $auth = setup_management_auth();
        if (!$auth) {
            \core\notification::error(get_string('systemauthfailed', 'mod_crucible'));
            redirect($returnurl);
        }

        $duration = optional_param('duration', 60, PARAM_INT);
        if ($duration < 1) {
            $duration = 60;
        }
        if ($duration > 10080) {
            $duration = 10080;
        }

        $extended = 0;

        foreach ($userids as $userid) {
            $attempt = $DB->get_record('crucible_attempts', [
                'crucibleid' => $crucible->id,
                'userid' => $userid,
                'state' => \mod_crucible\crucible_attempt::INPROGRESS,
            ], '*', IGNORE_MULTIPLE);

            if (!$attempt || empty($attempt->eventid)) {
                continue;
            }

            try {
                $event = get_event($auth, $attempt->eventid);
            } catch (\Exception $e) {
                debugging("Could not retrieve event {$attempt->eventid}: " . $e->getMessage(), DEBUG_DEVELOPER);
                continue;
            }

            if (!$event || empty($event->expirationDate)) {
                continue;
            }

            $timestamp = new DateTime($event->expirationDate);
            $timestamp->add(new DateInterval('PT' . $duration . 'M'));
            $event->expirationDate = $timestamp->format('Y-m-d\TH:i:s.u\Z');

            $result = extend_event($auth, $event);
            if ($result) {
                $attempt->endtime = strtotime($event->expirationDate);
                $attempt->timemodified = time();
                $DB->update_record('crucible_attempts', $attempt);
                $extended++;
            }
        }

        \core\notification::success(get_string('labsextended', 'mod_crucible', $extended));
        redirect($returnurl);
        break;

    default:
        throw new moodle_exception('invalidaction', 'mod_crucible');
}
