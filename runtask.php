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

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once("$CFG->dirroot/mod/crucible/locallib.php");

require_login();
require_sesskey();

$id = required_param('id', PARAM_ALPHANUMEXT);
$a = required_param('a', PARAM_ALPHANUMEXT);
$cmid = required_param('cmid', PARAM_INT);

if ($cmid) {
    $cm         = get_coursemodule_from_id('crucible', $cmid, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $crucible   = $DB->get_record('crucible', array('id' => $cm->instance), '*', MUST_EXIST);
}

$url = new moodle_url('/mod/crucible/getresults.php', array('cmid' => $cm->id, 'id' => $id));
$PAGE->set_url($url);

// Require the session key - want to make sure that this isn't called
// maliciously to keep a session alive longer than intended.
if (!confirm_sesskey()) {
    header('HTTP/1.1 403 Forbidden');
    print_error('invalidsesskey');
}

$system = setup_system();
$results = run_task($system, $id);
$response = array();

// if (!$results) {
//     header('HTTP/1.1 500 Error');
//     $response['message'] = "error";
// } else if (is_array($results)) {
//     global $DB;
//     header('HTTP/1.1 200 OK');
//     // TODO status of first result is not indicative of succes on all vms
//     $response['status'] = $results[0]->status;
//     $response['message'] = "success";

//     $task = get_task($system, $id);

//     $dbtask = $DB->get_record_sql('SELECT * from {crucible_tasks} WHERE '
//             . $DB->sql_compare_text('name') . ' = '
//             . $DB->sql_compare_text(':name'), ['name' => $task->name]);

//     if ($dbtask !== false) {
//         $succeeded =  0;
//         // save results in the db
//         foreach ($results as $result) {
//             // these results will not include child tasks
//             $entry = new stdClass();
//             $entry->taskid = $dbtask->id;
//             $entry->dispatchtaskid = $result->taskId;
//             $entry->attemptid = $a;
//             debugging("received $result->status for $result->vmName and output $result->actualOutput", DEBUG_DEVELOPER);
//             $entry->vmname = $result->vmName;
//             $entry->status = $result->status;
//             if ($result->status === "succeeded") {
//                 $entry->score = $dbtask->points;
//                 $succeeded++;
//             } else {
//                 $entry->score = 0;
//             }
//             $entry->timemodified = time();
//             $rec = $DB->insert_record('crucible_task_results', $entry);
//             if ($rec === false) {
//                 debugging("failed to insert task results record for " . $id, DEBUG_DEVELOPER);
//             }
//             if ($succeeded === 0) {
//                 $response['status'] = "failed";
//             } else if ($succeeded === count($results)) {
//                 $response['status'] = "succeeded";
//             } else {
//                 $response['status'] = "partial";
//             }

//             // score should be a percentage of points based on how many vms passed
//             $taskscore = ($succeeded / count($results)) * $dbtask->points;
// 	    // check for existing record for this task
//             //$summary = $DB->get_record("crucible_task_results", array("taskid" => $dbtask->id, "attemptid" => $a, "vmname" => "SUMMARY"));
//             $summary = $DB->get_record_sql('SELECT * from {crucible_task_results} WHERE '
//                     . 'taskid = ' . $dbtask->id . ' AND '
//                     . 'attemptid = ' . $a . ' AND '
//                     . $DB->sql_compare_text('vmname') . ' = '
//                     . $DB->sql_compare_text(':vmname'), ['vmname' => 'SUMMARY']);

//             if (is_null($summary) || $summary === false) {
//                 debugging("we are missing a summary task result", DEBUG_DEVELOPER);
// /*
//                 $entry = new stdClass();
//                 $entry->taskid = $dbtask->id;
//                 $entry->dispatchtaskid = $id;;
//                 $entry->attemptid = $a;
//                 $entry->status = $response['status'];
//                 $entry->score = $taskscore;
//                 $entry->vmname = "SUMMARY";
//                 $entry->timemodified = time();
//                 $rec = $DB->insert_record('crucible_task_results', $entry);
// */
//             } else {
//                 $summary->status = $response['status'];
//                 $summary->score = $taskscore;
//                 $summary->timemodified = time();
//                 $rec = $DB->update_record('crucible_task_results', $summary);
//             }
//             if ($rec === false) {
//                 debugging("failed to insert master task results record for " . $id, DEBUG_DEVELOPER);
//             }

//             $response['taskscore'] = $taskscore;
//             debugging("task score should be $taskscore", DEBUG_DEVELOPER);
//         }
//     }

//     // update attempt grade
//     $object = new \mod_crucible\crucible($cm, $course, $crucible, $url);
//     $object->get_open_attempt();
//     $grader = new \mod_crucible\utils\grade($object);
//     $score = $grader->calculate_attempt_grade($object->openAttempt);
//     $response['score'] = get_string("attemptscore", "crucible") . $score;
//     debugging("grade " . $score, DEBUG_DEVELOPER);

//     //$response['raw'] = $result;
// } else {
//     header('HTTP/1.1 200 OK');
//     $response['detail'] = $results->detail;
//     $response['message'] = "error";
// }
$response['id'] = $id;
$response['a'] = $a;

echo json_encode($response);
