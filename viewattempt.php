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

$a = required_param('a', PARAM_INT);  // attempt ID 

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
$url = new moodle_url ( '/mod/crucible/view.php', array ( 'id' => $cm->id ) );

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

$renderer->display_form($url, $object->crucible->eventtemplateid);

if ($showgrade) {
    $renderer->display_grade($crucible);
}

// TODO loads tasks and results from the db
global $DB;

$isinstructor = has_capability('mod/crucible:manage', $context);

//get tasks from db
if ($isinstructor) {
    echo "Displaying all gradable tasks";
    $tasks = $DB->get_records('crucible_tasks', array("crucibleid" => $crucible->id, "gradable" => "1"));
} else {
    echo "Displaying all visible and gradable tasks";
    $tasks = $DB->get_records('crucible_tasks', array("crucibleid" => $crucible->id, "visible" => "1", "gradable" => "1"));
}

foreach ($tasks as $task) {
    $results = $DB->get_records('crucible_task_results', array("attemptid" => $a, "taskid" => $task->id));
    if ($results === false) {
        continue;
    }
    foreach ($results as $result) {
        //$totalpoints += $task->points;
        //$totalslotpoints += $result->score;
        $task->score = $result->score;
        $task->result = $result->status;
    }
}

if ($tasks) {
    $renderer->display_results($tasks);
}

echo $renderer->footer();


