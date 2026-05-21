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

// Sort users
usort($users, function($a, $b) use ($sort, $dir) {
    $val1 = $a->$sort ?? '';
    $val2 = $b->$sort ?? '';
    $cmp = strcasecmp($val1, $val2);
    return $dir === 'DESC' ? -$cmp : $cmp;
});

$coursecontext = context_course::instance($course->id);
$userroles = [];
foreach ($users as $u) {
    $roles = get_user_roles($coursecontext, $u->userid);
    $rolenames = [];
    foreach ($roles as $role) {
        $rolenames[] = role_get_name($role, $coursecontext);
    }
    $userroles[$u->userid] = implode(', ', $rolenames);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_deployments_pageheading', 'crucible'));

// Check for active deployments and show adhoc task link
$has_active_deploys = false;
foreach ($users as $u) {
    if (in_array($u->deploystatus, ['pending', 'launched'])) {
        $has_active_deploys = true;
        break;
    }
}

if ($has_active_deploys) {
    $adhocurl = new moodle_url('/admin/tool/task/adhoctasks.php', [
        'classname' => '\\mod_crucible\\task\\bulkdeploy_run'
    ]);
    echo $OUTPUT->notification(
        'Deployments are running. ' . html_writer::link($adhocurl, 'View adhoc task details', ['target' => '_blank']),
        \core\output\notification::NOTIFY_INFO
    );
}

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

$statuslegend = s(implode("\n", [
    get_string('status_legend_none', 'crucible'),
    get_string('status_legend_scheduled', 'crucible'),
    get_string('status_legend_pending', 'crucible'),
    get_string('status_legend_launched', 'crucible'),
    get_string('status_legend_failed', 'crucible'),
    get_string('status_legend_cancelled', 'crucible'),
    get_string('status_legend_inprogress', 'crucible'),
    get_string('status_legend_finished', 'crucible'),
    get_string('status_legend_abandoned', 'crucible'),
    get_string('status_legend_overdue', 'crucible'),
]));
$statusheader = $sortlink('attemptstate', 'Status') .
    ' <span title="' . $statuslegend . '" class="mod-crucible-status-tooltip">ⓘ</span>';

echo html_writer::start_tag('table', ['class' => 'generaltable mod-crucible-users-table']);
echo '<thead><tr>';
echo '<th><input type="checkbox" id="select-all-checkbox"></th>';
echo '<th>' . $sortlink('firstname', 'User') . '</th>';
echo '<th>Role</th>';
echo '<th>' . $statusheader . '</th>';
echo '<th>Current or Last Event</th>';
echo '<th>Scheduled For</th>';
echo '<th>Actions</th>';
echo '</tr></thead><tbody>';

foreach ($users as $u) {
    $fullname = s($u->firstname . ' ' . $u->lastname);

    $statusinfo = 'None';

    // Priority: scheduled > active deployments > attempt status > other deployment status
    if (!empty($u->deploystatus) && !empty($u->scheduledfor) && $u->scheduledfor > time() && $u->deploystatus === 'pending') {
        // Show "Scheduled" for future deployments
        $statusinfo = 'Scheduled';
    } else if (!empty($u->deploystatus) && in_array($u->deploystatus, ['pending', 'launched'])) {
        // Show active deployment status (overrides attempt status)
        $statusinfo = ucfirst($u->deploystatus);
    } else if (!empty($u->attemptid)) {
        // Show attempt status - map to Crucible states
        $statemap = [
            'inprogress' => 'In Progress',
            'finished' => 'Finished',
            'abandoned' => 'Abandoned',
            'overdue' => 'Overdue',
        ];
        $statusinfo = $statemap[$u->attemptstate] ?? ucfirst($u->attemptstate ?? 'unknown');
    } else if (!empty($u->deploystatus)) {
        // Show other deployment statuses (ready, failed, cancelled)
        $statusinfo = ucfirst($u->deploystatus);
    }

    // Show eventid: deployment eventid OR attempt eventid as fallback
    $eventid = '';
    if (!empty($u->deploygamespaceid)) {
        // Deployment has an eventid
        $eventid = $u->deploygamespaceid;
    } else if (!empty($u->attemptgamespaceid)) {
        // Fall back to attempt eventid
        $eventid = $u->attemptgamespaceid;
    }
    $eventtext = $eventid ? s($eventid) : '─';

    // Show scheduled time if deployment is scheduled (pending with future time)
    $scheduledtext = '─';
    if (!empty($u->scheduledfor) && $u->scheduledfor > time() && $u->deploystatus === 'pending') {
        $scheduledtext = userdate($u->scheduledfor, get_string('strftimedatetime', 'langconfig'));
    }

    $roletext = isset($userroles[$u->userid]) ? s($userroles[$u->userid]) : '─';

    // Build status cell with error tooltip if failed
    $statushtml = s($statusinfo);
    if ($statusinfo === 'Failed' && !empty($u->deployerror)) {
        $errormsg = s($u->deployerror);
        $statushtml = '<span title="' . $errormsg . '" class="mod-crucible-status-tooltip">' .
                      s($statusinfo) . ' ⓘ</span>';
    } else if ($statusinfo === 'In Progress' && (!empty($u->attempttimestart) || !empty($u->attemptendtime))) {
        $tooltipparts = [];
        $datefmt = get_string('strftimedatetime', 'langconfig');
        if (!empty($u->attempttimestart)) {
            $tooltipparts[] = get_string('status_active_at', 'crucible', userdate($u->attempttimestart, $datefmt));
        }
        if (!empty($u->attemptendtime)) {
            $tooltipparts[] = get_string('status_ends_at', 'crucible', userdate($u->attemptendtime, $datefmt));
        }
        $tooltip = s(implode("\n", $tooltipparts));
        $statushtml = '<span title="' . $tooltip . '" class="mod-crucible-status-tooltip">' .
                      s($statusinfo) . ' ⓘ</span>';
    }

    echo '<tr data-userid="' . $u->userid . '" data-status="' . s(strtolower($statusinfo)) . '">';
    echo '<td><input type="checkbox" class="user-checkbox" value="' . $u->userid . '"></td>';
    echo '<td>' . $fullname . '</td>';
    echo '<td>' . $roletext . '</td>';
    echo '<td>' . $statushtml . '</td>';
    echo '<td>' . $eventtext . '</td>';
    echo '<td>' . $scheduledtext . '</td>';
    echo '<td>';

    // Show link to view attempt if there's an attempt (inprogress or finished)
    if (!empty($u->attemptid) && in_array($u->attemptstate, ['inprogress', 'finished', 'abandoned', 'overdue'])) {
        $viewurl = new moodle_url('/mod/crucible/view.php', [
            'id' => $cmid,
        ]);
        echo html_writer::link($viewurl, get_string('viewattempt', 'crucible'), ['class' => 'btn btn-sm btn-outline-primary', 'target' => '_blank']);
    } else {
        echo '─';
    }

    echo '</td>';
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
