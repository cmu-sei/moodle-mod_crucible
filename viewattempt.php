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

use \mod_crucible\crucible;

//require('../../config.php');
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->dirroot/mod/crucible/lib.php");
require_once("$CFG->dirroot/mod/crucible/locallib.php");

$a = optional_param('a', '', PARAM_INT);  // attempt ID 
$action = optional_param('action', 'list', PARAM_TEXT);
$actionitem = optional_param('id', 0, PARAM_INT);

if (!$a) {
    $a = required_param('attemptid', PARAM_INT);  // attempt ID
}

try {
        $attempt    = $DB->get_record('crucible_attempts', array('id' => $a), '*', MUST_EXIST);
        $crucible   = $DB->get_record('crucible', array('id' => $attempt->crucibleid), '*', MUST_EXIST);
        $course     = $DB->get_record('course', array('id' => $crucible->course), '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('crucible', $crucible->id, $course->id, false, MUST_EXIST);
} catch (Exception $e) {
    print_error("invalid attempt id passed");
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
// TODO create review attempt capability
require_capability('mod/crucible:view', $context);

// TODO log event attempt views
if ($_SERVER['REQUEST_METHOD'] == "GET") {
    // Completion and trigger events.
    //crucible_view($crucible, $course, $cm, $context);
}

// Print the page header.
$url = new moodle_url ( '/mod/crucible/viewattempt.php', array ( 'a' => $a ) );

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string($crucible->name));
$PAGE->set_heading($course->fullname);

// new crucible class
$pageurl = null;
$pagevars = array();
$object = new \mod_crucible\crucible($cm, $course, $crucible, $pageurl, $pagevars);

// get eventtemplate info
$object->eventtemplate = get_eventtemplate($object->userauth, $crucible->eventtemplateid);

// Update the database.
if ($object->eventtemplate) {
    $scenariotemplateid = $object->eventtemplate->scenarioTemplateId;
    // Update the database.
    $crucible->name = $object->eventtemplate->name;
    $crucible->intro = $object->eventtemplate->description;
    $DB->update_record('crucible', $crucible);
    // this generates lots of hvp module errors
    //rebuild_course_cache($crucible->course);
} else {
    $scenariotemplateid = "";
}

//TODO send instructor to a different page where manual grading can occur

$eventid = null;
$viewid = null;
$scenarioid = null;
$startime = null;
$endtime = null;

$grader = new \mod_crucible\utils\grade($object);
$gradepass = $grader->get_grade_item_passing_grade();
debugging("grade pass is $gradepass", DEBUG_DEVELOPER);

// show grade only if a passing grade is set
if ((int)$gradepass >0) {
    $showgrade = true;
} else {
    $showgrade = false;
}

$renderer = $PAGE->get_renderer('mod_crucible');
echo $renderer->header();
$renderer->display_detail($crucible, $object->eventtemplate->durationHours);


$isinstructor = has_capability('mod/crucible:manage', $context);

if ($isinstructor) {
    // TODO display attempt user with formatting
    $user = $DB->get_record('user', array("id" => $attempt->userid));
    echo "Username: " . fullname($user);
}

$renderer->display_form($url, $object->crucible->eventtemplateid);

global $DB;



//get tasks from db
if ($isinstructor) {

    if ($showgrade) {
        $renderer->display_grade($crucible, $attempt->userid);
        $renderer->display_score($attempt->id);
    }

    // Get the editgrade form.
    $mform = new \mod_crucible\crucible_editgrade_form();

    // If the cancel button was pressed, we are out of here.
    if ($mform->is_cancelled()) {
        redirect($PAGE->url, get_string('cancelled'), 2);
        exit;
    }

    // If we have data, then our job here is to save it and return.
    if ($data = $mform->get_data()) {
        $data->vmname = "SUMMARY";
        debugging("updating crucible_task_results", DEBUG_DEVELOPER);
        $DB->update_record('crucible_task_results', $data);
        $attempt = $object->get_attempt($a);
	$score = $grader->calculate_attempt_grade($attempt);
	$response['score'] = get_string("attemptscore", "crucible") . $score;
	debugging("grade " . $score, DEBUG_DEVELOPER);

        redirect($PAGE->url, get_string('updated', 'core', 'grade item; new grade ' . $score), 2);
    }


    // If the action is specified as "edit" then we show the edit form.
    if ($action == "edit") {
        // Create some data for our form and set it to the form.
        $data = new stdClass();
        // get task from db table
        $data = $DB->get_record_sql('SELECT * from {crucible_task_results} WHERE '
                . 'taskid = ' . $actionitem . ' AND '
                . 'attemptid = ' . $a . ' AND '
                . $DB->sql_compare_text('vmname') . ' = '
                . $DB->sql_compare_text(':vmname'), ['vmname' => 'SUMMARY']);

        if (!$data) { // In case there isn't any data in your chosen table.
            print_error("this should not happen");
        }

        $mform->set_data($data);
        // Header for the page.

        echo $renderer->heading('Edit Task Grade', 3);

        // Output page and form.
        $mform->display();
    }

    echo "<br>Instructor view: displaying all gradable tasks";
    $tasks = $DB->get_records('crucible_tasks', array("crucibleid" => $crucible->id, "gradable" => "1"));

    $details = array();
    foreach ($tasks as $task) {
        $task_results = array();
        $results = $DB->get_records('crucible_task_results', array("attemptid" => $a, "taskid" => $task->id), "timemodified ASC");
        foreach ($results as $result) {
            $newtask = clone $task;
            $newtask->vmname = $result->vmname;
            $newtask->score = $result->score;
            $newtask->result = $result->status;
            if (isset($result->comment)) {
                $newtask->comment = $result->comment;
            }
            if ($newtask->vmname === 'SUMMARY') {
                array_unshift($task_results, $newtask);
            } else {
                $task_results[] = $newtask;
            }
	}
        $details = array_merge($details, $task_results);
    }

    $renderer->display_results_detail($a, $details);

} else {

    if ($showgrade) {
        $renderer->display_grade($crucible);
        $renderer->display_score($attempt->id);
    }
    echo "<br>Student view: displaying all visible and gradable tasks";
    $tasks = $DB->get_records('crucible_tasks', array("crucibleid" => $crucible->id, "visible" => "1", "gradable" => "1"));

    foreach ($tasks as $task) {
        $results = $DB->get_records('crucible_task_results', array("attemptid" => $a, "taskid" => $task->id), "timemodified ASC");
        if ($results === false) {
            continue;
        }
        // TODO handle multiple results
        // TODO show vm summary task only
        foreach ($results as $result) {
            $task->score = $result->score;
            $task->result = $result->status;
            if (!is_null($result->comment)) {
                $task->comment = $result->comment;
            }
        }
    }

    if ($tasks) {
        $renderer->display_results($tasks, $review = true);
    }
}

