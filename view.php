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

/**
Crucible Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
DM20-0196
 */

//require('../../config.php');
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->dirroot/mod/crucible/lib.php");
require_once("$CFG->dirroot/mod/crucible/locallib.php");
require_once($CFG->libdir . '/completionlib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$c = optional_param('c', 0, PARAM_INT);  // instance ID - it should be named as the first character of the module.

if ($id) {
    $cm         = get_coursemodule_from_id('crucible', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $crucible   = $DB->get_record('crucible', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($c) {
    $crucible   = $DB->get_record('crucible', array('id' => $c), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $crucible->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('crucible', $crucible->id, $course->id, false, MUST_EXIST);
} else {
    print_error('courseorinstanceid', 'crucible');
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/crucible:view', $context);

if ($_SERVER['REQUEST_METHOD'] == "GET") {
    // Completion and trigger events.
    crucible_view($crucible, $course, $cm, $context);
}

// Print the page header.
$url = new moodle_url ( '/mod/crucible/view.php', array ( 'id' => $cm->id ) );

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string($crucible->name));
$PAGE->set_heading($course->fullname);

//echo $OUTPUT->header();

// get lab info
$eventtemplate = $crucible->eventtemplate;
$lab = get_eventtemplate($eventtemplate);

// Update the database.
if ($lab) {
    $scenarioid = $lab->scenarioId;
    // Update the database.
    $crucible->name = $lab->name;
    $crucible->intro = $lab->description;
    $DB->update_record('crucible', $crucible);
    rebuild_course_cache($crucible->course);
} else {
    $scenarioid = "";
}

// get current state of lab
$systemauth = setup();
$access_token = get_token($systemauth);
$scopes = get_scopes($systemauth);
$clientsecret = get_clientsecret($systemauth);
$clientid = get_clientid($systemauth);
$token_url = get_token_url();
$history = list_events($systemauth, $eventtemplate);
$launched = get_launched($history);

// attempt
$object = new \mod_crucible\crucible($cm, $course, $crucible, $pageurl, $pagevars);

$attempt = null;

// get active attempt for user
//$attempt = $object->get_open_attempt();


// handle button click
if ($_SERVER['REQUEST_METHOD'] == "POST" and isset($_POST['start']))
{
    // check not started already
    if (!$launched) {
        $event = start_event($systemauth, $eventtemplate);
        if ($event) {
            $launched = get_event($systemauth, $event);
        }
        crucible_start($cm, $context, $crucible);
        //$attempt = new crucible_attempt();

        var_dump($attempt);
    }
}
else if ($_SERVER['REQUEST_METHOD'] == "POST" and isset($_POST['stop']))
{
    if ($launched) {
        if ($launched->status == "Active") {
            stop_event($systemauth, $launched->id);
            $launched = get_event($systemauth, $launched->id);
            crucible_end($cm, $context, $crucible);
            //$attempt->close_attempt();
        }
    }

}

if ($launched) {
    $event = $launched->id;
    $exerciseid = $launched->exerciseId;
    $sessionid = $launched->sessionId;

    // TODO remove this check once the steamfitter is updated
    if (strpos($launched->launchDate, "Z")) {
        $starttime = strtotime($launched->launchDate);
    } else {
        $starttime = strtotime($launched->launchDate . 'Z');
    }
    if (strpos($launched->expirationDate, "Z")) {
        $endtime = strtotime($launched->expirationDate);
    } else {
        $endtime = strtotime($launched->expirationDate . 'Z');
    }
} else {
    $event = null;
    $exerciseid = null;
    $sessionid = null;
    $startime = null;
    $endtime = null;
}

// todo can this go in the check above?
if (is_object($launched)) {
    $status = $launched->status;
} else {
    $status = null;
}


// pull values from the settings
$alloy_api_url = get_config('crucible', 'alloyapiurl');
$vm_app_url = get_config('crucible', 'vmappurl');
$player_app_url = get_config('crucible', 'playerappurl');
$steamfitter_api_url = get_config('crucible', 'steamfitterapiurl');
$vmapp = $crucible->vmapp;
$showfailed = get_config('crucible', 'showfailed');

$renderer = $PAGE->get_renderer('mod_crucible');
echo $renderer->header();
$renderer->display_detail($crucible);
$renderer->display_form($url, $eventtemplate);

if ($launched) {

    // TODO add mod setting to pick format
    if ($crucible->clock == 1) {
        $renderer->display_clock($starttime, $endtime);
        $PAGE->requires->js_call_amd('mod_crucible/clock', 'countdown', array('endtime' => $endtime));
    } else if ($crucible->clock == 2) {
        $renderer->display_clock($starttime, $endtime);
        $PAGE->requires->js_call_amd('mod_crucible/clock', 'countup', array('starttime' => $starttime));
    }
}

if ($vmapp == 1) {
    $renderer->display_embed_page($crucible);
} else {
    $renderer->display_link_page($player_app_url, $exerciseid);
}

// TODO have a completely different view page for active labs
if ($sessionid) {
    $tasks = filter_tasks(get_sessiontasks($systemauth, $sessionid));
    $taskresults = get_taskresults($systemauth, $sessionid);

    // taskresults will be null if there are no results
    if ($taskresults) {
        foreach ($taskresults as $result) {
            // find task in tasks and update the result
            foreach ($tasks as $task) {
                if ($task->id == $result->dispatchTaskId) {
                    // TODO there may be more than one dispatchTaskResult
                    // for tasks that can be executed multiple times
                    $task->result = $result;
                }
            }
        }
    }
    $renderer->display_results($tasks);
    // start js to monitor task status
    $PAGE->requires->js_call_amd('mod_crucible/results', 'init', [
	'access_token' => $access_token,
	'session' => $sessionid,
	'steamfitter_api' => $steamfitter_api_url,
    ]);
} else if ($scenarioid) {
    // this is when we do not have an active session
    $tasks = filter_tasks(get_scenariotasks($systemauth, $scenarioid));

    $renderer->display_tasks($tasks);
}
$info = new stdClass();
$info->token = $access_token;
$info->state = $status;
$info->event = $event;
$info->exercise = $exerciseid;
$info->alloy_api_url = $alloy_api_url;
$info->vm_app_url = $vm_app_url;
$info->player_app_url = $player_app_url;

$PAGE->requires->js_call_amd('mod_crucible/view', 'init', ['info' => $info]);

echo $renderer->display_history($history, $showfailed);
echo $renderer->footer();


