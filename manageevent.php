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
 * Manage a single active Crucible event.
 *
 * @package    mod_crucible
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/mod/crucible/lib.php');
require_once($CFG->dirroot . '/mod/crucible/locallib.php');

$id = required_param('id', PARAM_INT);
$attemptid = optional_param('attempt', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'crucible');
require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
if (!has_any_capability(['mod/crucible:managelabs', 'mod/crucible:manage'], $context)) {
    require_capability('mod/crucible:manage', $context);
}

$crucible = $DB->get_record('crucible', ['id' => $cm->instance], '*', MUST_EXIST);

if ($attemptid) {
    $attempt = $DB->get_record('crucible_attempts', [
        'id' => $attemptid,
        'crucibleid' => $crucible->id,
    ], '*', MUST_EXIST);
} else {
    $attempt = $DB->get_record('crucible_attempts', [
        'crucibleid' => $crucible->id,
        'state' => \mod_crucible\crucible_attempt::INPROGRESS,
    ], '*', IGNORE_MULTIPLE);
}

$urlparams = ['id' => $cm->id];
if ($attempt) {
    $urlparams['attempt'] = $attempt->id;
}
$url = new moodle_url('/mod/crucible/manageevent.php', $urlparams);
$returnurl = new moodle_url('/mod/crucible/manage.php', ['id' => $cm->id]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string(get_string('manageevent', 'mod_crucible')));
$PAGE->set_heading($course->fullname);

$auth = setup_management_auth();
if (!$auth) {
    throw new moodle_exception('systemauthfailed', 'mod_crucible');
}

$event = null;
if ($attempt && !empty($attempt->eventid)) {
    try {
        $event = get_event($auth, $attempt->eventid);
    } catch (\Exception $e) {
        debugging("Could not retrieve event {$attempt->eventid}: " . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    if ($action === 'stop' && $attempt && !empty($attempt->eventid)) {
        if ($event && $event->status === 'Active') {
            stop_event($auth, $attempt->eventid);
        }

        $attempt->state = \mod_crucible\crucible_attempt::FINISHED;
        $attempt->timefinish = time();
        $attempt->timemodified = time();
        $DB->update_record('crucible_attempts', $attempt);

        redirect($returnurl, get_string('eventstopped', 'mod_crucible'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$renderer = $PAGE->get_renderer('mod_crucible');
echo $renderer->header();

if ($attempt && $event) {
    if (isset($event->launchDate) && strpos($event->launchDate, "Z") !== false) {
        $starttime = strtotime($event->launchDate);
    } else {
        $starttime = strtotime($event->launchDate . 'Z');
    }
    if (isset($event->expirationDate) && strpos($event->expirationDate, "Z") !== false) {
        $endtime = strtotime($event->expirationDate);
    } else {
        $endtime = strtotime($event->expirationDate . 'Z');
    }

    $users = [];
    $owner = $DB->get_record('user', ['id' => $attempt->userid]);
    if ($owner) {
        $users[] = fullname($owner);
    }
    $attemptusers = $DB->get_records('crucible_attempt_users', ['attemptid' => $attempt->id]);
    foreach ($attemptusers as $attemptuser) {
        $user = $DB->get_record('user', ['id' => $attemptuser->userid]);
        if ($user) {
            $users[] = fullname($user);
        }
    }

    $viewurl = null;
    $vmappurl = get_config('crucible', 'vmappurl');
    if (!empty($vmappurl) && !empty($event->viewId)) {
        $viewurl = rtrim($vmappurl, '/') . '/views/' . $event->viewId;
    }

    $renderer->display_manage_event($event, $starttime, $endtime, $users, $cm->id, $crucible, $attempt->id, $viewurl);
    $PAGE->requires->js_call_amd('mod_crucible/extend', 'init');
} else {
    echo html_writer::div(get_string('noactiveevent', 'mod_crucible'), 'alert alert-info mt-3');
    echo $OUTPUT->single_button($returnurl, get_string('backtomanagelabs', 'mod_crucible'));
}

echo $renderer->footer();
