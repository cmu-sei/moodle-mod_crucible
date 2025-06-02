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
 * crucible module main user interface
 *
 * @package    mod_crucible
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*
Crucible Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS.
CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING,
BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY,
OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY
OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.
Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
DM20-0196
 */

use mod_crucible\crucible;

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->dirroot/mod/crucible/lib.php");
require_once("$CFG->dirroot/mod/crucible/locallib.php");
require_once($CFG->libdir . '/completionlib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID.
$c = optional_param('c', 0, PARAM_INT);  // Instance ID - it should be named as the first character of the module.
$attemptid = optional_param('attempt', 0, PARAM_INT);
$code = optional_param('code', '', PARAM_TEXT);

try {
    if ($id) {
        $cm         = get_coursemodule_from_id('crucible', $id, 0, false, MUST_EXIST);
        $course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $crucible   = $DB->get_record('crucible', ['id' => $cm->instance], '*', MUST_EXIST);
    } else if ($c) {
        $crucible   = $DB->get_record('crucible', ['id' => $c], '*', MUST_EXIST);
        $course     = $DB->get_record('course', ['id' => $crucible->course], '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('crucible', $crucible->id, $course->id, false, MUST_EXIST);
    }
} catch (Exception $e) {
    throw new moodle_exception(null, '', '', null, 'Invalid course module ID passed');
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/crucible:view', $context);

if ($_SERVER['REQUEST_METHOD'] == "GET") {
    // Completion and trigger events.
    crucible_view($crucible, $course, $cm, $context);
}

// Print the page header.
$url = new moodle_url ( '/mod/crucible/view.php', ['id' => $cm->id]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string($crucible->name));
$PAGE->set_heading($course->fullname);

// New crucible class.
$pageurl = null;
$pagevars = [];
$object = new \mod_crucible\crucible($cm, $course, $crucible, $pageurl, $pagevars);

// Enlist if code in url.
if ($code != null) {
    $object->enlist($code);
}

// Get eventtemplate info.
$object->eventtemplate = get_eventtemplate($object->userauth, $crucible->eventtemplateid);

// Update the database.
if ($object->eventtemplate) {
    $scenariotemplateid = $object->eventtemplate->scenarioTemplateId;
    // Update the database.
    $crucible->name = $object->eventtemplate->name;

    if (!$crucible->intro) {
        $crucible->intro = $object->eventtemplate->description;
    }

    $DB->update_record('crucible', $crucible);
    // This generates lots of hvp module errors.
    // rebuild_course_cache($crucible->course);
} else {
    $scenariotemplateid = "";
}

// Get current state of eventtemplate.
$accesstoken = get_token($object->userauth);
$history = $object->list_events();
$object->events = get_active_events($history);

if ($object->events) {
    ensure_added_to_event_attempts($object->events);
}

// Get active attempt for user: true/false.
$attempt = $object->get_open_attempt($attemptid);
if ($attempt == true) {
    debugging("get_open_attempt returned " . $object->openattempt->id, DEBUG_DEVELOPER);
} else if ($attempt == false) {
    debugging("get_open_attempt returned false", DEBUG_DEVELOPER);
}

// TODO send instructor to a different page.

// Handle start/stop form action.
if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['start_confirmed']) && $_POST['start_confirmed'] === "yes") {
    debugging("start request received", DEBUG_DEVELOPER);

    if ($attempt) { // && (!$object->event !== null)
        // TODO this should also check that we dont have an attempt.
        if ($object->event && $object->isended()) {
            debugging('closing attempt - not active', DEBUG_DEVELOPER);
            $grader = new \mod_crucible\utils\grade($object);
            $grader->process_attempt($object->openattempt);
            $object->openattempt->close_attempt();
        }
    }

    // Check not started already.
    if (!$object->event) {
        $eventid = start_event($object->userauth, $object->crucible->eventtemplateid);
        if ($eventid) {
            debugging("new event created $eventid", DEBUG_DEVELOPER);
            $object->event = get_event($object->userauth, $eventid);
            $attempt = $object->init_attempt();
            debugging("init_attempt returned $attempt", DEBUG_DEVELOPER);
            if (!$attempt) {
                debugging("init_attempt failed");
                throw new moodle_exception(null, '', '', null, 'init_attempt failed');
            }
            crucible_start($cm, $context, $crucible);
        } else {
            debugging("start_event failed", DEBUG_DEVELOPER);
            throw new moodle_exception(null, '', '', null, 'start_event failed');
        }
    }
} else if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['stop_confirmed']) && $_POST['stop_confirmed'] === "yes") {
    debugging("stop request received", DEBUG_DEVELOPER);
    if ($object->event) {
        if ($object->event->status == "Active") {
            if (!$attempt) {
                debugging('no attempt to close', DEBUG_DEVELOPER);
                throw new moodle_exception(null, '', '', null, 'no attempt to close');
            }

            $grader = new \mod_crucible\utils\grade($object);
            $grader->process_attempt($object->openattempt);
            $object->openattempt->close_attempt();

            stop_event($object->userauth, $object->event->id);
            $object->event = get_event($object->userauth, $object->event->id);
            // Why call this again? just to check that it is ending.
            debugging("stop_attempt called, get_event returned " . $object->event->status, DEBUG_DEVELOPER);
            crucible_end($cm, $context, $crucible);
        }
    }
    $reviewurl = new moodle_url('/mod/crucible/review.php', [
        'id' => $cm->id,
    ]);
    redirect($reviewurl);
}

if ($object->event) {
    if (($object->event->status === "Active") && (!$attempt)) {
        debugging("active event with no attempt", DEBUG_DEVELOPER);
        // print_error('eventwithoutattempt', 'crucible');
        // TODO give user a popup to confirm they are starting an attempt.
        $attempt = $object->init_attempt();
    }
}
if ((!$object->event) && ($attempt)) {
    debugging("active attempt with no event", DEBUG_DEVELOPER);
    // print_error('attemptalreadyexists', 'crucible');
    $grader = new \mod_crucible\utils\grade($object);
    $grader->process_attempt($object->openattempt);
    $object->openattempt->close_attempt();
}

if ($object->event) {
    $eventid = $object->event->id;
    $viewid = $object->event->viewId;
    $scenarioid = $object->event->scenarioId;

    // TODO remove this check once the steamfitter is updated.
    if (strpos($object->event->launchDate, "Z")) {
        $starttime = strtotime($object->event->launchDate);
    } else {
        $starttime = strtotime($object->event->launchDate . 'Z');
    }
    if (strpos($object->event->expirationDate, "Z")) {
        $endtime = strtotime($object->event->expirationDate);
    } else {
        $endtime = strtotime($object->event->expirationDate . 'Z');
    }
} else {
    if ($attempt) {
        // print_error('attemptalreadyexists', 'crucible');
        debugging('closing attempt - not active', DEBUG_DEVELOPER);
        $grader = new \mod_crucible\utils\grade($object);
        $grader->process_attempt($object->openattempt);
        $object->openattempt->close_attempt();
    }
    $eventid = null;
    $viewid = null;
    $scenarioid = null;
    $startime = null;
    $endtime = null;
}

// Todo can this go in the check above?
if (is_object($object->event)) {
    $status = $object->event->status;
} else {
    $status = null;
}


// Pull values from the settings.
$alloyapiurl = get_config('crucible', 'alloyapiurl');
$vmappurl = get_config('crucible', 'vmappurl');
$playerappurl = get_config('crucible', 'playerappurl');
$steamfitterapiurl = get_config('crucible', 'steamfitterapiurl');
$vmapp = $crucible->vmapp;

$grader = new \mod_crucible\utils\grade($object);
$gradepass = $grader->get_grade_item_passing_grade();
debugging("grade pass is $gradepass", DEBUG_DEVELOPER);

// Show grade only if a passing grade is set.
if ((int)$gradepass > 0) {
    $showgrade = true;
} else {
    $showgrade = false;
}

$renderer = $PAGE->get_renderer('mod_crucible');
echo $renderer->header();

$renderer->display_detail($crucible, $object->eventtemplate->durationHours);

$formattempts = $object->get_all_attempts_for_form();

$sharecode = '';

if ($object->openattempt && $object->openattempt->userid == $USER->id) {
    if ($object->event->shareCode == null) {
        $object->event = $object->generate_sharecode();
    }
    if (is_object($object->event) && isset($object->event->shareCode)) {
        $sharecode = $object->event->shareCode;
    } else {
        debugging('Event is not set or sharecode is missing', DEBUG_DEVELOPER);
    }
}

if ($object->event) {

    $extend = false;
    if ($object->systemauth && $crucible->extendevent) {
        $extend = true;
    }

    // TODO add mod setting to pick format.
    if ($crucible->clock == 1) {
        $renderer->display_clock($starttime, $endtime, $extend);
        $PAGE->requires->js_call_amd('mod_crucible/clock', 'countdown', ['endtime' => $endtime]);
    } else if ($crucible->clock == 2) {
        $renderer->display_clock($starttime, $endtime, $extend);
        $PAGE->requires->js_call_amd('mod_crucible/clock', 'countup', ['starttime' => $starttime]);
    }
    // No matter what, start our session timer.
    $PAGE->requires->js_call_amd('mod_crucible/clock', 'init', ['endtime' => $endtime, 'id' => $object->event->id]);
} else if ($showgrade) {
    $renderer->display_grade($crucible);
}

$renderer->display_form($url, $object->crucible->eventtemplateid, $id, $attemptid, $formattempts, $sharecode);

$PAGE->requires->js_call_amd('mod_crucible/invite', 'init', [['id' => $cm->id]]);

// TODO have a completely different view page for active labs.
if ($object->event && $object->event->status === 'Active' && $scenarioid) {

    $renderer->display_invite($url, $object->crucible->eventtemplateid, $id, $attemptid, $formattempts, $sharecode);

    if ($vmapp == 1) {
        $renderer->display_embed_page($crucible);
    } else {
        $renderer->display_link_page($playerappurl, $viewid);
    }

    $tasks = get_scenariotasks($object->userauth, $scenarioid);
    if (is_null($tasks)) {
        $tasks = get_scenariotasks($object->systemauth, $scenarioid);
    }

    if ($tasks) {
        // Display tasks.
        $filtered = $object->filter_scenario_tasks($tasks, true, false);
        $renderer->display_results($filtered, $review = false);
        $info = new stdClass();
        $info->scenario = $scenarioid;
        $info->view = $viewid;
        $info->attempt = $object->openattempt->id;
        $info->cmid = $cm->id;
        // Have run task button hit an ajax script on server to run as system.
        $PAGE->requires->js_call_amd('mod_crucible/tasks', 'init', [$info]);

        $renderer->display_score($object->openattempt->id);
    }
}

$PAGE->requires->js_call_amd('mod_crucible/view', 'init');

$configdata = [
    'token' => $accesstoken,
    'state' => $status,
    'event' => $eventid,
    'view' => $viewid,
    'alloy_api_url' => $alloyapiurl,
    'vm_app_url' => $vmappurl,
    'player_app_url' => $playerappurl,
];

$PAGE->requires->js_init_code("
    window.CrucibleConfig = " . json_encode($configdata) . ";
");

$jsoptions = ['keepaliveinterval' => 1];
$PAGE->requires->js_call_amd('mod_crucible/keepalive', 'init', [$jsoptions]);


echo $renderer->footer();
