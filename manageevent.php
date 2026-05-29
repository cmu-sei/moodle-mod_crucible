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
 * Manage Event page for the Crucible activity module.
 *
 * @package    mod_crucible
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_crucible\crucible;

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->dirroot/mod/crucible/lib.php");
require_once("$CFG->dirroot/mod/crucible/locallib.php");

$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

try {
    $cm = get_coursemodule_from_id('crucible', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $crucible = $DB->get_record('crucible', ['id' => $cm->instance], '*', MUST_EXIST);
} catch (Exception $e) {
    throw new moodle_exception('invalidcoursemodule', 'error');
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/crucible:manage', $context);

$url = new moodle_url('/mod/crucible/manageevent.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string(get_string('manageevent', 'mod_crucible')));
$PAGE->set_heading($course->fullname);

$pageurl = $url;
$pagevars = [];
$object = new \mod_crucible\crucible($cm, $course, $crucible, $pageurl, $pagevars);

// Find the active event from the attempt record.
$activeattempts = $DB->get_records('crucible_attempts', [
    'crucibleid' => $crucible->id,
    'state' => \mod_crucible\crucible_attempt::INPROGRESS,
]);
$event = null;
foreach ($activeattempts as $att) {
    if (!empty($att->eventid)) {
        $event = get_event($object->userauth, $att->eventid);
        if ($event) {
            break;
        }
    }
}

// Handle POST actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    if ($action === 'stop' && $event && $event->status === 'Active') {
        stop_event($object->userauth, $event->id);
        // Close any open attempts.
        $activeattempts = $DB->get_records('crucible_attempts', [
            'crucibleid' => $crucible->id,
            'state' => \mod_crucible\crucible_attempt::INPROGRESS,
        ]);
        foreach ($activeattempts as $att) {
            $attemptobj = new \mod_crucible\crucible_attempt($att);
            $grader = new \mod_crucible\utils\grade($object);
            $grader->process_attempt($attemptobj);
            $attemptobj->close_attempt();
        }
        redirect($url, get_string('eventstopped', 'mod_crucible'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    if ($action === 'restart' && $event) {
        // Stop current event.
        if ($event->status === 'Active') {
            stop_event($object->userauth, $event->id);
            $activeattempts = $DB->get_records('crucible_attempts', [
                'crucibleid' => $crucible->id,
                'state' => \mod_crucible\crucible_attempt::INPROGRESS,
            ]);
            foreach ($activeattempts as $att) {
                $attemptobj = new \mod_crucible\crucible_attempt($att);
                $grader = new \mod_crucible\utils\grade($object);
                $grader->process_attempt($attemptobj);
                $attemptobj->close_attempt();
            }
        }
        // Start new event.
        $eventid = start_event($object->userauth, $crucible->eventtemplateid);
        if ($eventid) {
            redirect($url, get_string('eventrestarted', 'mod_crucible'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            redirect($url, get_string('eventrestartfailed', 'mod_crucible'), null, \core\output\notification::NOTIFY_ERROR);
        }
    }
}

$renderer = $PAGE->get_renderer('mod_crucible');
echo $renderer->header();

$hasactiveevent = ($event && $event->status === 'Active');

if ($hasactiveevent) {
    // Parse times.
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

    // Get users in this event's attempts.
    $userids = array_map(function($a) { return $a->userid; }, $activeattempts);
    $users = [];
    foreach ($userids as $uid) {
        $user = $DB->get_record('user', ['id' => $uid]);
        if ($user) {
            $users[] = fullname($user);
        }
    }

    $renderer->display_manage_event($event, $starttime, $endtime, $users, $cm->id, $crucible);
    $PAGE->requires->js_call_amd('mod_crucible/extend', 'init');
} else {
    echo '<div class="alert alert-info mt-3">' . get_string('noactiveevent', 'mod_crucible') . '</div>';
}

echo $renderer->footer();
