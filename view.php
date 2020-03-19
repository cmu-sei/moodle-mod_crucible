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
$n = optional_param('n', 0, PARAM_INT);  // instance ID - it should be named as the first character of the module.

if ($id) {
    $cm         = get_coursemodule_from_id('crucible', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $crucible    = $DB->get_record('crucible', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $crucible    = $DB->get_record('crucible', array('id' => $n), '*', MUST_EXIST);
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

echo $OUTPUT->header();

// get lab info
$definition = $crucible->definition;
$lab = get_definition($definition);

// TODO update name and intro in the db
// Update the database.
$crucible->name = $lab->name;
$crucible->intro = $lab->description;
$DB->update_record('crucible', $crucible);
rebuild_course_cache($crucible->course);

// get current state of lab
$systemauth = setup();
$access_token = get_token($systemauth);
$scopes = get_scopes($systemauth);
$clientsecret = get_clientsecret($systemauth);
$clientid = get_clientid($systemauth);
$token_url = get_token_url();
$history = list_implementations($systemauth, $definition);
$launched = get_launched($history);
//if ($launched) {
//    $launched = get_implementation($systemauth, $launched['id']);
//}

// handle button click
if ($_SERVER['REQUEST_METHOD'] == "POST" and isset($_POST['start']))
{
    // check not started already
    if (!$launched) {
        $implementation = start_implementation($systemauth, $definition);
        if ($implementation) {
            $launched = get_implementation($systemauth, $implementation);
        }
        crucible_start($cm, $context, $crucible);
    }
}
else if ($_SERVER['REQUEST_METHOD'] == "POST" and isset($_POST['stop']))
{
    if ($launched->status == "Active") {
        stop_implementation($systemauth, $launched->id);
        $launched = get_implementation($systemauth, $launched->id);
        crucible_end($cm, $context, $crucible);

    }

}

if ($launched) {
    $implementation = $launched->id;
    $exerciseid = $launched->exerciseId;
} else {
    $implementation = null;
    $exerciseid = null;
}

// TODO write a renderer
//$renderer = $PAGE->get_renderer('mod_crucible');

// pull values from the settings
$alloy_api_url = get_config('crucible', 'alloyapiurl');
$vm_app_url = get_config('crucible', 'vmappurl');
$player_app_url = get_config('crucible', 'playerappurl');
$vmapp = get_config('crucible', 'vmapp');
$vmapp = $crucible->vmapp;

//echo '<h1>' . $lab->name . '</h1>';
//echo $lab->description . '<br><br>';
echo '<h1>' . $crucible->name . '</h1>';
echo $crucible->intro . '<br><br>';

echo '<form action="' . $url . '" method="post">';
//echo '<input type="hidden" id="implementation" name="implementation" value="' . $implementation . '"/>';
//echo '<input id="end_button" style="display:none" class="btn btn-primary" type="submit" name="stop" value="End Lab"' . $implementation . '" />';
echo '<input type="hidden" id="implementation" name="implementation" value=""/>';
echo '<input id="end_button" style="display:none" class="btn btn-primary" type="submit" name="stop" value="End Lab" />';
echo '<input type="hidden" id="definition" name="definition" value="' . $definition . '" />';
echo '<input id="launch_button" style="display:none" class="btn btn-primary" type="submit" name="start" value="Launch Lab" />';
echo '</form>';
echo '<input id="wait" style="display:none; width:400px" class="btn btn-primary" type="" name="start" value="Please wait, system processing" />';
echo '<input id="failed" style="display:none; width:400px" class="btn btn-primary" type="" name="failed" value="Your operation has failed." />';

echo '<br>';

// TODO dont create these urls in the intitial page because the url is incomplete
// TODO maybe give them link to alloy instead?
if ($vmapp == 1) {
//    echo '<div align="center" class="container">';
//    echo '<iframe id="vm_or_link" class="iframe-class" style="display:none; background-color:white" src=""></iframe>';
//    echo '</div><br>';
    echo '<div align="center" class="iframe-container">';
    echo '<iframe id="vm_or_link" src="" allowfullscreen></iframe>';
    echo '</div><br>';

} else {
    echo '<a href="' . $player_app_url . '/exercise-player/' .  $exerciseid . '" id="vm_or_link" style="display:none; width:400px" target="_blank" class="btn btn-primary">Click here to open player in a new tab</a>';
}

echo '<br>';

// Display history
echo '<h3>History</h3>';
echo '<table style="width:100%"><tr><th>id</th><th>status</th><th>launchDate</th><th>endDate</th></tr>';
for ($index = count($history) - 1; $index >= 0; $index--) {
    $odx = $history[$index];
    echo "<tr><td>" . $odx['id'] . "</td><td>" . $odx['status'] . "</td><td>" . $odx['launchDate'] .  "</td><td>" . $odx['endDate'] . "</td></tr>";
}
echo "</table>";
echo "<style>
.container {
    width: 1024px;
    height: 768px;
}
.iframe-class {
    width: 100%;
    height: 100%;
    resize: both;
    overflow: auto;
}

/* new below here */
.iframe-container {
    overflow: hidden;
    padding-top: 56.25%;
    position: relative;
}

.iframe-container iframe {
    border: 0;
    height: 100%;
    left: 0;
    position: absolute;
    top: 0;
    width: 100%;
    background-color: white;
    display: none;
}

/* 4x3 Aspect Ratio */
.iframe-container-4x3 {
    padding-top: 75%;
}
</style>
";

//TODO move js into amd
$PAGE->requires->js_call_amd('mod_crucible/crucibleview', 'init');

if (is_object($launched)) {
    $status = $launched->status;
} else {
    $status = null;
}

$refresh_token = null;

echo '<script type="text/javascript">';
echo 'var access_token = "' . $access_token . '";';
echo 'var refresh_token = "' . $refresh_token . '";';
echo 'var id = "' . $implementation . '";';
echo 'var definition = "' . $definition . '";';
echo 'var alloy_api_url = "' . $alloy_api_url . '";';
echo 'var vm_app_url = "' . $vm_app_url . '";';
echo 'var player_app_url = "' . $player_app_url . '";';
echo 'var exerciseid = "' . $exerciseid . '";';
echo 'var status = "' . $status . '";';
echo 'var token_url = "' . $token_url . '";';
echo 'var scopes = "' . $scopes . '";';
echo 'var clientsecret = "' . $clientsecret . '";';
echo 'var clientid = "' . $clientid . '";';


echo "</script>";


echo $OUTPUT->footer();
// load js module



