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

$id = required_param('id', PARAM_ALPHANUMEXT);

// Require the session key - want to make sure that this isn't called
// maliciously to keep a session alive longer than intended.
if (!confirm_sesskey()) {
    header('HTTP/1.1 403 Forbidden');
    throw new \moodle_exception('invalidsesskey', 'error');
}

$response = [];

$system = setup_system();
$event = get_event($system, $id);
if (!$event) {
    header('HTTP/1.1 500 Error');
    $response['message'] = "error with get_event";
} else {
    $data = $event;
    $response['oldtime'] = $event->expirationDate;
    $timestamp = new DateTime($event->expirationDate);
    $timestamp->add(new DateInterval('PT1H'));
    $posttime = $timestamp->format('Y-m-d\TH:i:s.u\Z');
    $response['posttime'] = $posttime;
    $data->expirationDate = $posttime;
    $result = extend_event($system, $data);
    if (!$result) {
        header('HTTP/1.1 500 Error');
        $response['message'] = "error with extend_event";
        $response['event'] = $event;
        $response['data'] = $data;
    } else {
        header('HTTP/1.1 200 OK');
        $response['message'] = "success";
    }
}
$response['id'] = $id;

echo json_encode($response);


