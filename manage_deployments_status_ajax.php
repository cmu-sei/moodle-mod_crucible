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
    'has_active' => !empty($activejobs),
    'jobs' => [],
    'updated_users' => [],
];

foreach ($activejobs as $job) {
    $progress = $manrepo->get_job_progress($job->id);
    $response['jobs'][] = [
        'jobid' => $job->id,
        'status' => $job->status,
        'progress' => [
            'ready' => $progress->ready,
            'failed' => $progress->failed,
            'pending' => $progress->pending,
            'launched' => $progress->launched,
            'total' => $job->totalusers,
        ],
    ];
}

$users = $manrepo->get_enrolled_users_with_state($crucible->id, $course->id);
foreach ($users as $u) {
    if (!empty($u->deploystatus) || !empty($u->attemptid)) {
        $response['updated_users'][] = [
            'userid' => $u->userid,
            'deploystatus' => $u->deploystatus ?? null,
            'deploygamespaceid' => $u->deploygamespaceid ?? null,
            'attemptid' => $u->attemptid ?? null,
            'attemptstate' => $u->attemptstate ?? null,
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($response);
