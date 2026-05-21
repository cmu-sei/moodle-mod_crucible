<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/crucible/locallib.php');

use mod_crucible\local\bulkdeploy\job_repository;
use mod_crucible\task\bulkdeploy_run;

$action = required_param('action', PARAM_ALPHANUMEXT);
$cmid = required_param('id', PARAM_INT);
require_sesskey();

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'crucible');
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/crucible:manage', $context);

$crucible = $DB->get_record('crucible', ['id' => $cm->instance], '*', MUST_EXIST);
$returnurl = new moodle_url('/mod/crucible/manage_deployments.php', ['id' => $cmid]);

switch ($action) {
    case 'deploy_selected':
        $userids = required_param('userids', PARAM_TEXT);
        $userids = array_filter(array_map('intval', explode(',', $userids)));

        if (empty($userids)) {
            \core\notification::error(get_string('no_users_selected', 'crucible'));
            redirect($returnurl);
        }

        $repo = new job_repository();
        $jobid = $repo->create_job(
            (int) $crucible->id,
            (int) $course->id,
            (int) $USER->id,
            1, // Always batchsize=1 for Crucible (sequential)
            null, // rolefilter not used in action
            $userids,
            null // scheduledfor = null (immediate)
        );

        $task = new bulkdeploy_run();
        $task->set_custom_data((object) ['jobid' => $jobid]);
        $task->set_component('mod_crucible');
        $task->set_next_run_time(time()); // Run immediately
        \core\task\manager::queue_adhoc_task($task);

        \core\notification::success(get_string('deployment_queued', 'crucible', count($userids)));
        redirect($returnurl);
        break;

    case 'schedule_selected':
        $userids = required_param('userids', PARAM_TEXT);
        $scheduledfor = required_param('scheduledfor', PARAM_INT);
        $userids = array_filter(array_map('intval', explode(',', $userids)));

        if (empty($userids)) {
            \core\notification::error(get_string('no_users_selected', 'crucible'));
            redirect($returnurl);
        }

        if ($scheduledfor <= time()) {
            \core\notification::error(get_string('schedule_past_error', 'crucible'));
            redirect($returnurl);
        }

        $repo = new job_repository();
        $jobid = $repo->create_job(
            (int) $crucible->id,
            (int) $course->id,
            (int) $USER->id,
            1, // Always batchsize=1 for Crucible (sequential)
            null,
            $userids,
            $scheduledfor // Schedule for future time
        );

        $task = new bulkdeploy_run();
        $task->set_custom_data((object) ['jobid' => $jobid]);
        $task->set_component('mod_crucible');
        $task->set_next_run_time($scheduledfor);
        \core\task\manager::queue_adhoc_task($task);

        \core\notification::success(get_string('deployment_scheduled', 'crucible', count($userids)));
        redirect($returnurl);
        break;

    case 'cancel_selected':
        $userids = required_param('userids', PARAM_TEXT);
        $userids = array_filter(array_map('intval', explode(',', $userids)));

        if (empty($userids)) {
            \core\notification::error(get_string('no_users_selected', 'crucible'));
            redirect($returnurl);
        }

        $auth = setup_system();
        $cancelled = 0;
        $jobsToCancel = [];

        foreach ($userids as $uid) {
            $deployrow = $DB->get_record_sql(
                "SELECT bdu.*, bdj.id as jobid, bdj.scheduledfor
                 FROM {crucible_bulkdeploy_user} bdu
                 INNER JOIN {crucible_bulkdeploy_job} bdj ON bdj.id = bdu.jobid
                 WHERE bdu.userid = :userid
                 AND bdj.crucibleid = :crucibleid
                 AND bdu.status IN ('pending', 'launched')
                 ORDER BY bdu.id DESC
                 LIMIT 1",
                ['userid' => $uid, 'crucibleid' => $crucible->id]
            );

            if ($deployrow) {
                // If event was launched, stop it
                if (!empty($deployrow->eventid) && $auth) {
                    try {
                        stop_event($auth, $deployrow->eventid);
                    } catch (Exception $e) {
                        debugging("Failed to stop event {$deployrow->eventid}: " . $e->getMessage(), DEBUG_DEVELOPER);
                    }
                }

                $repo = new job_repository();
                $repo->set_user_status($deployrow->id, 'cancelled', 'Manually cancelled', '');
                $cancelled++;

                // Track jobs that need adhoc task cancellation
                if (!empty($deployrow->scheduledfor)) {
                    $jobsToCancel[$deployrow->jobid] = true;
                }
            }
        }

        // Cancel adhoc tasks for scheduled jobs
        foreach (array_keys($jobsToCancel) as $jobid) {
            // Find matching tasks
            $tasks = $DB->get_records('task_adhoc', [
                'component' => 'mod_crucible',
                'classname' => '\\mod_crucible\\task\\bulkdeploy_run'
            ]);
            foreach ($tasks as $task) {
                $data = json_decode($task->customdata);
                if (!empty($data->jobid) && $data->jobid == $jobid) {
                    $DB->delete_records('task_adhoc', ['id' => $task->id]);
                }
            }
        }

        \core\notification::success(get_string('deployments_cancelled', 'crucible', $cancelled));
        redirect($returnurl);
        break;

    case 'end_selected':
        $userids = required_param('userids', PARAM_TEXT);
        $userids = array_filter(array_map('intval', explode(',', $userids)));

        if (empty($userids)) {
            \core\notification::error(get_string('no_users_selected', 'crucible'));
            redirect($returnurl);
        }

        $auth = setup_system();
        if (!$auth) {
            \core\notification::error('Could not initialize Crucible API');
            redirect($returnurl);
        }

        $ended = 0;

        foreach ($userids as $uid) {
            $attempt = $DB->get_record('crucible_attempts', [
                'crucibleid' => $crucible->id,
                'userid' => $uid,
                'state' => 'inprogress',
            ], '*', IGNORE_MULTIPLE);

            if ($attempt && !empty($attempt->eventid)) {
                try {
                    stop_event($auth, $attempt->eventid);
                    $attempt->state = 'finished';
                    $attempt->timefinish = time();
                    $attempt->timemodified = time();
                    $DB->update_record('crucible_attempts', $attempt);
                    $ended++;
                } catch (Exception $e) {
                    debugging("Failed to end attempt for user $uid: " . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        }

        \core\notification::success(get_string('attempts_ended', 'crucible', $ended));
        redirect($returnurl);
        break;

    default:
        throw new moodle_exception('Invalid action');
}
