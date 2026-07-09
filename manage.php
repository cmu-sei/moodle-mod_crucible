<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Instructor lab management page for the Crucible activity module.
 *
 * @package    mod_crucible
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/crucible/locallib.php');

use mod_crucible\local\lab_management_repository;

$cmid = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('c', 0, PARAM_INT);
$selectedrole = optional_param('rolefilter', 0, PARAM_INT);
$rolefilter = $selectedrole ? [$selectedrole] : [];
$sort = optional_param('sort', 'firstname', PARAM_ALPHA);
$dir = strtoupper(optional_param('dir', 'ASC', PARAM_ALPHA));

if (!$cmid && $courseid) {
    $url = new moodle_url('/course/view.php', ['id' => $courseid]);
    throw new moodle_exception('activitymanagementrequirescmid', 'mod_crucible', $url);
}

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'crucible');
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
if (!has_any_capability(['mod/crucible:managelabs', 'mod/crucible:manage'], $context)) {
    require_capability('mod/crucible:manage', $context);
}

$crucible = $DB->get_record('crucible', ['id' => $cm->instance], '*', MUST_EXIST);

$allowedsorts = ['firstname', 'lastname', 'roletext', 'attemptstate', 'scheduledfor', 'attemptendtime'];
if (!in_array($sort, $allowedsorts, true)) {
    $sort = 'firstname';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'ASC';
}

$PAGE->set_url('/mod/crucible/manage.php', ['id' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('managelabs', 'mod_crucible'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->js_call_amd('mod_crucible/manage', 'init', [$cmid, sesskey()]);

$repo = new lab_management_repository();
$users = $repo->get_enrolled_users_with_state($crucible->id, $course->id, $rolefilter);

$coursecontext = context_course::instance($course->id);
$userroles = [];
foreach ($users as $user) {
    $roles = get_user_roles($coursecontext, $user->userid);
    $rolenames = [];
    foreach ($roles as $role) {
        $rolenames[] = role_get_name($role, $coursecontext);
    }
    $userroles[$user->userid] = implode(', ', $rolenames);
    $user->roletext = $userroles[$user->userid];
}

usort($users, function($a, $b) use ($sort, $dir) {
    $val1 = $a->$sort ?? '';
    $val2 = $b->$sort ?? '';
    if (is_numeric($val1) || is_numeric($val2)) {
        $cmp = (int) $val1 <=> (int) $val2;
    } else {
        $cmp = strcasecmp((string) $val1, (string) $val2);
    }
    return $dir === 'DESC' ? -$cmp : $cmp;
});

echo $OUTPUT->header();

$roleopts = [0 => get_string('rolefilterall', 'mod_crucible')];
foreach (get_roles_used_in_context($context) as $role) {
    $roleopts[$role->id] = role_get_name($role, $context);
}

echo html_writer::start_div('mod-crucible-manage crucible-card');
echo html_writer::start_div('crucible-card__header');
echo html_writer::empty_tag('img', [
    'src' => $OUTPUT->image_url('icon', 'mod_crucible'),
    'alt' => '',
    'class' => 'crucible-card__icon crucible-card__icon-img',
]);
echo html_writer::start_div('crucible-card__titlewrap');
echo html_writer::div(get_string('managelabs', 'mod_crucible'), 'crucible-card__title');
echo html_writer::div(format_string($crucible->name), 'crucible-card__subtitle');
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::start_div('crucible-card__body');

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url, 'class' => 'crucible-filterbar']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cmid]);
echo html_writer::start_div('crucible-filterbar__field');
echo html_writer::tag('label', get_string('rolefilter', 'mod_crucible') . ':', [
    'for' => 'rolefilter',
    'class' => 'crucible-filterbar__label',
]);
echo html_writer::select($roleopts, 'rolefilter', $selectedrole, false, [
    'id' => 'rolefilter',
    'class' => 'form-control crucible-filterbar__select',
]);
echo html_writer::end_div();
echo html_writer::tag('button', get_string('filter'), [
    'type' => 'submit',
    'class' => 'btn btn-outline-secondary crucible-utility-btn',
]);
echo html_writer::end_tag('form');

echo html_writer::start_div('bulk-actions crucible-toolbar');
echo html_writer::tag('button', get_string('selectall', 'mod_crucible'), [
    'id' => 'select-all-btn',
    'class' => 'btn btn-sm btn-outline-secondary crucible-utility-btn',
    'type' => 'button',
]);
echo ' ';
echo html_writer::tag('button', get_string('deselectall', 'mod_crucible'), [
    'id' => 'deselect-all-btn',
    'class' => 'btn btn-sm btn-outline-secondary crucible-utility-btn',
    'type' => 'button',
]);
echo ' ';
echo html_writer::tag('button', get_string('extendselected', 'mod_crucible'), [
    'id' => 'extend-selected-btn',
    'class' => 'btn btn-primary',
    'type' => 'button',
    'disabled' => 'disabled',
]);
echo ' ';
echo html_writer::tag('button', get_string('scheduleselected', 'mod_crucible'), [
    'id' => 'schedule-selected-btn',
    'class' => 'btn btn-primary',
    'type' => 'button',
    'disabled' => 'disabled',
]);
echo ' ';
echo html_writer::tag('button', get_string('endselected', 'mod_crucible'), [
    'id' => 'end-selected-btn',
    'class' => 'btn btn-danger',
    'type' => 'button',
    'disabled' => 'disabled',
]);
echo html_writer::end_div();

$sortlink = function($col, $label) use ($PAGE, $sort, $dir, $selectedrole) {
    $newdir = ($sort === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $url = new moodle_url($PAGE->url, ['sort' => $col, 'dir' => $newdir, 'rolefilter' => $selectedrole]);
    $suffix = $sort === $col ? ' ' . strtolower($dir) : '';
    return html_writer::link($url, $label . $suffix);
};

echo html_writer::start_div('crucible-table-wrap');
echo html_writer::start_tag('table', ['class' => 'generaltable mod-crucible-users-table']);
echo '<thead><tr>';
echo '<th><input type="checkbox" id="select-all-checkbox"></th>';
echo '<th>' . $sortlink('firstname', get_string('user')) . '</th>';
echo '<th>' . $sortlink('roletext', get_string('role')) . '</th>';
echo '<th>' . $sortlink('attemptstate', get_string('status')) . '</th>';
echo '<th>' . get_string('currentorlastlab', 'mod_crucible') . '</th>';
echo '<th>' . $sortlink('scheduledfor', get_string('scheduledfor', 'mod_crucible')) . '</th>';
echo '<th>' . $sortlink('attemptendtime', get_string('labendtime', 'mod_crucible')) . '</th>';
echo '<th>' . get_string('actions') . '</th>';
echo '</tr></thead><tbody>';

foreach ($users as $user) {
    $state = $repo->format_user_state($user, $cmid);
    $fullname = fullname($DB->get_record('user', ['id' => $user->userid], '*', MUST_EXIST));
    $roletext = $userroles[$user->userid] ?: '-';
    $endtext = !empty($user->attemptendtime)
        ? userdate($user->attemptendtime, get_string('strftimedatetime', 'langconfig'))
        : '-';
    $active = ($user->attemptstate ?? '') === \mod_crucible\crucible_attempt::INPROGRESS;

    echo '<tr data-userid="' . (int) $user->userid . '" data-status="' . s($state['status_class']) . '">';
    echo '<td><input type="checkbox" class="user-checkbox" value="' . (int) $user->userid . '"></td>';
    echo '<td>' . s($fullname) . '</td>';
    echo '<td>' . s($roletext) . '</td>';
    echo '<td class="cell-status">' . $state['status_html'] . '</td>';
    echo '<td class="cell-event">' . s($state['event_text']) . '</td>';
    echo '<td class="cell-scheduled">' . s($state['scheduled_text']) . '</td>';
    echo '<td class="cell-endtime">' . s($endtext) . '</td>';
    echo '<td class="cell-actions">' . $state['action_html'] . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
echo html_writer::end_div();

echo html_writer::start_tag('form', [
    'id' => 'extend-form',
    'method' => 'post',
    'action' => new moodle_url('/mod/crucible/manage_action.php'),
    'style' => 'display:none;',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'extend_selected']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cmid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userids', 'id' => 'extend-userids']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'duration', 'id' => 'extend-duration']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::end_tag('form');

echo html_writer::start_tag('form', [
    'id' => 'schedule-form',
    'method' => 'post',
    'action' => new moodle_url('/mod/crucible/manage_action.php'),
    'style' => 'display:none;',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'schedule_selected']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cmid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userids', 'id' => 'schedule-userids']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'scheduledfor', 'id' => 'schedule-timestamp']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'scheduledtimezone', 'id' => 'schedule-timezone']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::end_tag('form');

echo html_writer::start_tag('form', [
    'id' => 'end-form',
    'method' => 'post',
    'action' => new moodle_url('/mod/crucible/manage_action.php'),
    'style' => 'display:none;',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'end_selected']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cmid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userids', 'id' => 'end-userids']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::end_tag('form');

echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
