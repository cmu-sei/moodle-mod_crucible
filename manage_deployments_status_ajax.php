<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/crucible/locallib.php');

use mod_crucible\local\bulkdeploy\management_repository;
use mod_crucible\local\bulkdeploy\job_status;

$cmid = required_param('cmid', PARAM_INT);
require_sesskey();

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'crucible');
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/crucible:manage', $context);

$crucible = $DB->get_record('crucible', ['id' => $cm->instance], '*', MUST_EXIST);

$manrepo = new management_repository();
$activejobs = $manrepo->get_active_jobs($crucible->id);

$response = [
    'has_active'       => !empty($activejobs),
    'jobs'             => [],
    'updated_users'    => [],
    'progress_summary' => ['ready' => 0, 'total' => 0],
];

foreach ($activejobs as $job) {
    $progress = $manrepo->get_job_progress($job->id);
    $response['jobs'][] = [
        'jobid'    => $job->id,
        'status'   => $job->status,
        'progress' => [
            'ready'    => $progress->ready,
            'failed'   => $progress->failed,
            'pending'  => $progress->pending,
            'launched' => $progress->launched,
            'total'    => $job->totalusers,
        ],
    ];
    $response['progress_summary']['ready'] += (int) $progress->ready;
    $response['progress_summary']['total'] += (int) $job->totalusers;
}

$users = $manrepo->get_enrolled_users_with_state($crucible->id, $course->id);
foreach ($users as $u) {
    $u->cmid = $cmid;
    $state = $manrepo->format_user_state($u);

    $response['updated_users'][] = [
        'userid'         => (int) $u->userid,
        'status_label'   => $state['status_label'],
        'status_class'   => $state['status_class'],
        'event_text'     => $state['event_text'],
        'scheduled_text' => $state['scheduled_text'],
        'tooltip_html'   => $state['tooltip_html'],
        'action_html'    => $state['action_html'],
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
