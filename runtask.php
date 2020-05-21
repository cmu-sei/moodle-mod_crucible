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

// Require the session key - want to make sure that this isn't called
// maliciously to keep a session alive longer than intended.
if (!confirm_sesskey()) {
    header('HTTP/1.1 403 Forbidden');
    print_error('invalidsesskey');
}

$system = setup_system();
$result = run_task($system, $id);
$response = array();

if (!$result) {
    header('HTTP/1.1 500 Error');
    $response['message'] = "error";
} else if (is_array($result)) {
    global $DB;
    header('HTTP/1.1 200 OK');
    $response['status'] = $result[0]->status;
    $response['message'] = "success";

    $task = get_task($system, $id);

    $dbtask = $DB->get_record_sql('SELECT * from {crucible_tasks} WHERE '
            . $DB->sql_compare_text('name') . ' = '
            . $DB->sql_compare_text(':name'), ['name' => $task->name]);

    if ($dbtask !== false) {
    // save results in the db
/*
        <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="taskid" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false" COMMENT="The id of the task"/>
        <FIELD NAME="attemptid" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false" COMMENT="The id of the crucible attempt"/>
        <FIELD NAME="vmname" TYPE="text" NOTNULL="false" SEQUENCE="false" COMMENT="The vm name for this result"/>
        <FIELD NAME="status" TYPE="text" NOTNULL="false" SEQUENCE="false" COMMENT="The steamfitter task status"/>
        <FIELD NAME="score" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false" COMMENT="Score given to the student"/>
        <FIELD NAME="comment" TYPE="text" NOTNULL="false" SEQUENCE="false" COMMENT="A comment added by a teacher"/>
*/

        foreach ($result as $res) {
            $entry = new stdClass();
            $entry->taskid = $dbtask->id;
            $entry->attemptid = $a;
            $entry->vmname = $res->vmName;
            $entry->status = $res->status;
            if ($res->status === "succeeded") {
                $entry->score = $dbtask->points;
                $response['score'] = $dbtask->points;
            }
            $rec = $DB->insert_record('crucible_task_results', $entry);
            if ($rec === false) {
                debugging("failed to insert task results record for " . $id, DEBUG_DEVELOPER);
            }
        }
    }
    //$response['raw'] = $result;
} else {
    header('HTTP/1.1 200 OK');
    $response['detail'] = $result->detail;
    $response['message'] = "error";
}
$response['id'] = $id;
$response['a'] = $a;

echo json_encode($response);


