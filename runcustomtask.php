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

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once("$CFG->dirroot/mod/crucible/locallib.php");

require_login();
require_sesskey();

// TODO get the view id from an activity setting.
$id = required_param('id', PARAM_ALPHANUMEXT);

global $USER;
$username = fullname($USER);
$vmguid = null;

// TODO get the vm mask from an activity setting.
$vmmask = "mccorc.kali.student";

// Require the session key - want to make sure that this isn't called
// maliciously to keep a session alive longer than intended.
if (!confirm_sesskey()) {
    header('HTTP/1.1 403 Forbidden');
    throw new \moodle_exception('invalidsesskey', 'error');
}
$response = [];
$response['user'] = $username;

$system = setup_system();
// TODO we need to pass the view id here.
$result = get_allvms($system, $id);
foreach ($result as $vm) {
    if (preg_match("/$vmmask-$username/", $vm->name)) {
        $response['vm'] = $vm->id;
        $vmguid = $vm->id;
    }
}

$request = new stdClass();
$request->name = "Run VPL Check";
$request->description = "Run task to get VPL score";
$request->scenarioTemplateId = null;
$request->scenarioId = null;
$request->userId = null;
$request->action = "guest_process_run";
$request->vmMask = $vmguid;
$request->vmList = [];
$request->apiUrl = "stackstorm";
$inputString = new stdClass();
$inputString->Moid = "{moid}";
$inputString->Username = "root";
$inputString->Password = "tartans@1";
$inputString->CommandText = "/bin/bash";
$inputString->CommandArgs = '-c "hostname"';
$request->inputString = json_encode($inputString);
$request->expectedOutput = "";
$request->expirationSeconds = 0;
$request->delaySeconds = 0;
$request->intervalSeconds = 0;
$request->iterations = 0;
$request->triggerTaskId = null;
$request->triggerCondition = "Manual";

$data = json_encode($request);
$result = create_and_exec_task($system, $data);

if (!$result) {
    header('HTTP/1.1 500 Error');
    $response['message'] = "error";

} else {
    header('HTTP/1.1 200 OK');
    $response['status'] = $result[0]->status;
    $response['message'] = "success";
    $response['output'] = $result[0]->actualOutput;
}
echo json_encode($response);


