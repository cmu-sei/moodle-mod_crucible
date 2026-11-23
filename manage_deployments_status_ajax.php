<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Returns bulk-deployment progress for the manage deployments page as JSON.
 *
 * @package    mod_crucible
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
        'end_time_text'  => $state['end_time_text'],
        'tooltip_html'   => $state['tooltip_html'],
        'action_html'    => $state['action_html'],
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
