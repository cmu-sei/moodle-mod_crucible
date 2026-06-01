<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/crucible/locallib.php');

use mod_crucible\local\bulkdeploy\job_repository;
use mod_crucible\local\bulkdeploy\management_repository;

$cmid = required_param('id', PARAM_INT);
$rolefilter = optional_param_array('rolefilter', [], PARAM_INT);
$sort = optional_param('sort', 'firstname', PARAM_ALPHA);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'crucible');
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/crucible:manage', $context);

$crucible = $DB->get_record('crucible', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url('/mod/crucible/manage_deployments.php', ['id' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('manage_deployments_pageheading', 'crucible'));
$PAGE->set_heading(format_string($course->fullname));

$PAGE->requires->js_call_amd('mod_crucible/manage_deployments', 'init', [$cmid, sesskey()]);

$manrepo = new management_repository();
$users = $manrepo->get_enrolled_users_with_state($crucible->id, $course->id, $rolefilter);

$coursecontext = context_course::instance($course->id);
$userroles = [];
foreach ($users as $u) {
    $roles = get_user_roles($coursecontext, $u->userid);
    $rolenames = [];
    foreach ($roles as $role) {
        $rolenames[] = role_get_name($role, $coursecontext);
    }
    $userroles[$u->userid] = implode(', ', $rolenames);
    $u->roletext = $userroles[$u->userid];
    $u->cmid = $cmid;
}

// Sort users
usort($users, function($a, $b) use ($sort, $dir) {
    $val1 = $a->$sort ?? '';
    $val2 = $b->$sort ?? '';
    if ($sort === 'scheduledfor') {
        $val1 = (int)$val1;
        $val2 = (int)$val2;
        $cmp = $val1 <=> $val2;
    } else {
        $cmp = strcasecmp($val1, $val2);
    }
    return $dir === 'DESC' ? -$cmp : $cmp;
});

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_deployments_pageheading', 'crucible'));

// Check for active deployments and compute progress summary
$has_active_deploys = false;
foreach ($users as $u) {
    if (in_array($u->deploystatus, ['pending', 'launched'])) {
        $has_active_deploys = true;
        break;
    }
}

$summary_ready = 0;
$summary_total = 0;
if ($has_active_deploys) {
    $activejobs = $manrepo->get_active_jobs($crucible->id);
    foreach ($activejobs as $job) {
        $progress = $manrepo->get_job_progress($job->id);
        $summary_ready += (int) $progress->ready;
        $summary_total += (int) $job->totalusers;
    }
}

$adhocurl = new moodle_url('/admin/tool/task/adhoctasks.php', [
    'classname' => '\\mod_crucible\\task\\bulkdeploy_run',
]);
$linkhtml = html_writer::link($adhocurl, get_string('manage_deploy_running_link', 'crucible'),
    ['target' => '_blank']);
$progresshtml = html_writer::span((string) $summary_ready, 'deploy-summary-ready')
    . '/'
    . html_writer::span((string) $summary_total, 'deploy-summary-total');
$summaryhtml = get_string('manage_deploy_running_summary', 'crucible',
    (object) ['progress' => $progresshtml, 'link' => $linkhtml]);

$notifstyle = $has_active_deploys ? '' : 'display:none;';
echo html_writer::start_div('alert alert-info', [
    'id' => 'deploy-notification',
    'role' => 'alert',
    'style' => $notifstyle,
]);
echo $summaryhtml;
echo html_writer::end_div();

$roleopts = [0 => get_string('rolefilter_all', 'crucible')];
foreach (get_roles_used_in_context($context) as $role) {
    $roleopts[$role->id] = role_get_name($role, $context);
}

echo html_writer::start_div('mod-crucible-manage-deployments');

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url, 'class' => 'mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cmid]);
echo html_writer::tag('label', 'Role Filter: ', ['for' => 'rolefilter']);
echo html_writer::select($roleopts, 'rolefilter[]', $rolefilter, false, ['multiple' => 'multiple', 'size' => count($roleopts), 'style' => 'width: 200px;']);
echo ' ';
echo html_writer::tag('button', 'Filter', ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

echo html_writer::start_div('bulk-actions mb-3');
echo html_writer::tag('button', get_string('select_all', 'crucible'), [
    'id' => 'select-all-btn',
    'class' => 'btn btn-sm btn-secondary',
    'type' => 'button',
    'title' => get_string('select_all_help', 'crucible')
]);
echo ' ';
echo html_writer::tag('button', get_string('deselect_all', 'crucible'), [
    'id' => 'deselect-all-btn',
    'class' => 'btn btn-sm btn-secondary',
    'type' => 'button',
    'title' => get_string('deselect_all_help', 'crucible')
]);
echo ' ';
echo html_writer::tag('button', get_string('deploy_selected_now', 'crucible'), [
    'id' => 'deploy-selected-btn',
    'class' => 'btn btn-success',
    'type' => 'button',
    'disabled' => 'disabled',
    'title' => get_string('deploy_selected_help', 'crucible')
]);
echo ' ';
echo html_writer::tag('button', get_string('schedule_selected', 'crucible'), [
    'id' => 'schedule-selected-btn',
    'class' => 'btn btn-primary',
    'type' => 'button',
    'disabled' => 'disabled',
    'title' => get_string('schedule_selected_help', 'crucible')
]);
echo ' ';
echo html_writer::tag('button', get_string('cancel_selected', 'crucible'), [
    'id' => 'cancel-selected-btn',
    'class' => 'btn btn-warning',
    'type' => 'button',
    'disabled' => 'disabled',
    'title' => get_string('cancel_selected_help', 'crucible')
]);
echo ' ';
echo html_writer::tag('button', get_string('end_selected', 'crucible'), [
    'id' => 'end-selected-btn',
    'class' => 'btn btn-danger',
    'type' => 'button',
    'disabled' => 'disabled',
    'title' => get_string('end_selected_help', 'crucible')
]);
echo html_writer::end_div();

// Build sort links for column headers
$sorticon = $dir === 'ASC' ? '▲' : '▼';
$sortlink = function($col, $label) use ($PAGE, $sort, $dir, $sorticon, $rolefilter) {
    $newdir = ($sort === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $url = new moodle_url($PAGE->url, ['sort' => $col, 'dir' => $newdir, 'rolefilter' => $rolefilter]);
    $icon = ($sort === $col) ? ' ' . $sorticon : '';
    return html_writer::link($url, $label . $icon);
};

$statusheader = $sortlink('attemptstate', get_string('status', 'crucible')) .
    ' ' . $OUTPUT->help_icon('status', 'crucible');

echo html_writer::start_tag('table', ['class' => 'generaltable mod-crucible-users-table']);
echo '<thead><tr>';
echo '<th><input type="checkbox" id="select-all-checkbox"></th>';
echo '<th>' . $sortlink('firstname', 'User') . '</th>';
echo '<th>' . $sortlink('roletext', 'Role') . '</th>';
echo '<th>' . $statusheader . '</th>';
echo '<th>Current or Last Event</th>';
echo '<th>' . $sortlink('scheduledfor', 'Scheduled For') . '</th>';
echo '<th>Actions</th>';
echo '</tr></thead><tbody>';

foreach ($users as $u) {
    $fullname = s($u->firstname . ' ' . $u->lastname);
    $roletext = isset($userroles[$u->userid]) ? s($userroles[$u->userid]) : '─';

    $state = $manrepo->format_user_state($u);

    $statushtml = $state['tooltip_html'] !== null
        ? $state['tooltip_html']
        : s($state['status_label']);
    echo '<tr data-userid="' . $u->userid . '" data-status="' . s($state['status_class']) . '">';
    echo '<td><input type="checkbox" class="user-checkbox" value="' . $u->userid . '"></td>';
    echo '<td>' . $fullname . '</td>';
    echo '<td>' . $roletext . '</td>';
    echo '<td class="cell-status">' . $statushtml . '</td>';
    echo '<td class="cell-event">' . $state['event_text'] . '</td>';
    echo '<td class="cell-scheduled">' . $state['scheduled_text'] . '</td>';
    echo '<td class="cell-actions">' . $state['action_html'] . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';

// Hidden forms for modals (no batchsize input for Crucible - always 1)
echo html_writer::start_tag('form', ['id' => 'deploy-form', 'method' => 'post', 'action' => new moodle_url('/mod/crucible/manage_deployments_action.php'), 'style' => 'display:none;']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'deploy_selected']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cmid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userids', 'id' => 'deploy-userids']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::end_tag('form');

echo html_writer::start_tag('form', ['id' => 'schedule-form', 'method' => 'post', 'action' => new moodle_url('/mod/crucible/manage_deployments_action.php'), 'style' => 'display:none;']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'schedule_selected']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cmid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userids', 'id' => 'schedule-userids']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'scheduledfor', 'id' => 'schedule-timestamp']);
echo html_writer::end_tag('form');

// Modal content templates (no batchsize input for Crucible)
echo html_writer::start_div('', ['id' => 'deploy-modal-content', 'style' => 'display:none;']);
echo html_writer::tag('p', get_string('deploy_confirm_message', 'crucible'));
echo html_writer::end_div();

echo html_writer::start_div('', ['id' => 'schedule-modal-content', 'style' => 'display:none;']);
echo html_writer::tag('label', get_string('scheduledfor', 'crucible') . ':', ['for' => 'scheduledfor-input', 'class' => 'd-block mb-2']);
echo html_writer::empty_tag('input', [
    'type' => 'datetime-local',
    'id' => 'scheduledfor-input',
    'value' => '',
    'class' => 'form-control',
    'style' => 'width: 220px;',
    'required' => 'required'
]);
echo html_writer::tag('small', '', ['class' => 'form-text text-muted mb-3', 'id' => 'timezone-display']);
echo html_writer::end_div();

echo html_writer::end_div();
echo $OUTPUT->footer();
