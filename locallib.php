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
 * Private crucible module utility functions
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

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->dirroot/mod/crucible/lib.php");


function setup_system() {

    $issuerid = get_config('crucible', 'issuerid');
    if (!$issuerid) {
        debugging("crucible does not have issuerid set", DEBUG_DEVELOPER);
        return;
    }
    $issuer = \core\oauth2\api::get_issuer($issuerid);

    try {
        $client = \core\oauth2\api::get_system_oauth_client($issuer);
    } catch (Exception $e) {
        debugging("get_system_oauth_client failed with $e->errorcode", DEBUG_NORMAL);
    }
    if ($client === false) {
        debugging('Cannot connect as system account', DEBUG_NORMAL);
        $details = 'Cannot connect as system account';
        throw new \Exception($details);
        return;
    }
    return $client;
}


function setup() {
    global $PAGE;
    $issuerid = get_config('crucible', 'issuerid');
    if (!$issuerid) {
        print_error('no issuer set for plugin');
    }
    $issuer = \core\oauth2\api::get_issuer($issuerid);

    $wantsurl = $PAGE->url;
    $returnparams = ['wantsurl' => $wantsurl, 'sesskey' => sesskey(), 'id' => $issuerid];
    $returnurl = new moodle_url('/auth/oauth2/login.php', $returnparams);

    $client = \core\oauth2\api::get_user_oauth_client($issuer, $returnurl);

    if ($client) {
        if (!$client->is_logged_in()) {
            debugging('not logged in', DEBUG_DEVELOPER);
            redirect($client->get_login_url());
        }
    }

    return $client;
}

function get_eventtemplate($client, $id) {

    if ($client == null) {
        print_error('could not setup session');
    }

    // web request
    $url = get_config('crucible', 'alloyapiurl') . "/definitions/" . $id;
    //echo "GET $url<br>";

    $response = $client->get($url);

    if ($client->info['http_code'] !== 200) {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        print_error($client->info['http_code'] . " for $url " . $client->response['www-authenticate']);
    }

    if (!$response) {
        debugging('no response received by get_eventtemplate', DEBUG_DEVELOPER);
    }
    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);

    if (!$r) {
        debugging("could not find item by id", DEBUG_DEVELOPER);
        return;
    }

    return $r;
}

function tasksort($a, $b) {
    return strcmp($a->name, $b->name);
}

// filter for tasks the user can see and sort by name
function filter_tasks($tasks) {
    if (is_null($tasks)) {
        return;
    }
    $filtered = array();
    foreach ($tasks as $task) {
        // TODO show automatic checks
        //if ($task->gradable == "true") {
        //    $filtered[] = $task;
        //}
        // show manual tasks only
        //if ($task->triggerCondition == "Manual") {
        //    $filtered[] = $task;
        //}
        // TODO for now, show all
        $filtered[] = $task;
    }
    // sort the array by name
    usort($filtered, "tasksort");
    return $filtered;
}

function get_eventtemplates($client) {

    if ($client == null) {
        debugging('error with client in get_eventtemplates', DEBUG_DEVELOPER);;
        return;
    }

    // web request
    $url = get_config('crucible', 'alloyapiurl') . "/definitions";
    //echo "GET $url<br>";

    $response = $client->get($url);
    if (!$response) {
        debugging("no response received by get_eventtemplates for $url", DEBUG_DEVELOPER);
    }
    //echo "response:<br><pre>$response</pre>";
    if ($client->info['http_code']  !== 200) {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        return;
    }
    $r = json_decode($response);

    if (!$r) {
        debugging("could not find item by id", DEBUG_DEVELOPER);
        return;
    }
    return $r;
}

function start_event($client, $id) {

    if ($client == null) {
        debugging('error with client in start_event', DEBUG_DEVELOPER);;
        return;
    }

    // web request
    $url = get_config('crucible', 'alloyapiurl') . "/definitions/" . $id . "/implementations";
    //echo "POST $url<br>";

    $response = $client->post($url);
    if (!$response) {
        debugging('no response received by start_event response code ' , $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        return;
    }
    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        //echo "could not decode json<br>";
        return;
    }

    // success
    if ($client->info['http_code']  === 201) {
        return $r->id;
    }
    if ($client->info['http_code']  === 500) {
        //echo "response code ". $client->info['http_code'] . "<br>";
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
        print_error($r->detail);
    }

    return;
}


function stop_event($client, $id) {

    if ($client == null) {
        debugging('error with client in stop_event', DEBUG_DEVELOPER);;
        return;
    }

    // web request
    $url = get_config('crucible', 'alloyapiurl') . "/implementations/" . $id . "/end";
    //echo "DELETE $url<br>";

    $response = $client->delete($url);

    if ($client->info['http_code']  !== 204) {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
    }

    //if (!$response) {
        //throw new \Exception($response);
    //    return;
    //}
    //echo "response:<br><pre>$response</pre>";
    return;
}

function run_task($client, $id) {

    if ($client == null) {
        return;
    }

    // web request
    $url = get_config('crucible', 'steamfitterapiurl') . "/dispatchtasks/" . $id . "/execute";

    $response = $client->post($url);

    if ($client->info['http_code']  !== 200) {
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }

    $r = json_decode($response);

    return $r;
}

function extend_event($client, $data) {

    if ($client == null) {
        return;
    }

    // web request
    $url = get_config('crucible', 'alloyapiurl') . "/implementations/" . $data->id;
    $client->setHeader('Content-Type: application/json-patch+json');

    $response = $client->put($url, json_encode($data));

    if ($client->info['http_code']  !== 200) {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        return;
    }

    $r = json_decode($response);

    return $r;
}

function get_event($client, $id) {

    if ($client == null) {
        debugging('error with client in get_event', DEBUG_DEVELOPER);;
        return;
    }

    if ($id == null) {
        debugging('error with id in get_event', DEBUG_DEVELOPER);;
        return;
    }

    // web request
    $url = get_config('crucible', 'alloyapiurl') . "/implementations/" . $id;
    //echo "GET $url<br>";

    $response = $client->get($url);
    if (!$response) {
        debugging("no response received by get_event for $url", DEBUG_DEVELOPER);
        return;
    }
    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        debugging("could not decode json for $url", DEBUG_DEVELOPER);
        return;
    }

    if ($client->info['http_code']  === 200) {
        return $r;
    } else {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        print_error($r->detail);
    }
    return;
}

function get_scenariotasks($client, $id) {

    if ($client == null) {
        debugging('error with client in get_scenariotasks', DEBUG_DEVELOPER);;
        return;
    }

    if ($id == null) {
        debugging('error with id in get_scenariotasks', DEBUG_DEVELOPER);;
        return;
    }

    // web request
    $url = get_config('crucible', 'steamfitterapiurl') . "/scenarios/" . $id . "/dispatchtasks";
    //echo "GET $url<br>";

    $response = $client->get($url);
    if (!$response) {
        debugging('no response received by get_scenariotasks', DEBUG_DEVELOPER);
        return;
    }
    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        debugging("could not decode json", DEBUG_DEVELOPER);
        return;
    }

    if ($client->info['http_code']  === 200) {
        return $r;
    } else {
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }
    return;
}

function get_sessiontasks($client, $id) {

    if ($client == null) {
        debugging('error with client in get_sessiontasks', DEBUG_DEVELOPER);;
        return;
    }

    if ($id == null) {
        debugging('error with id in get_sessiontasks', DEBUG_DEVELOPER);;
        return;
    }

    // web request
    $url = get_config('crucible', 'steamfitterapiurl') . "/sessions/" . $id . "/dispatchtasks";
    //echo "GET $url<br>";

    $response = $client->get($url);
    if (!$response) {
        debugging('no response received by get_sessiontasks', DEBUG_DEVELOPER);
        return;
    }
    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        debugging("could not decode json", DEBUG_DEVELOPER);
        return;
    }

    if ($client->info['http_code']  === 200) {
        return $r;
    } else {
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }
    return;
}

function get_taskresults($client, $id) {

    if ($client == null) {
        debugging('error with client in get_taskresults', DEBUG_DEVELOPER);;
        return;
    }

    if ($id == null) {
        debugging('error with client in get_taskresults', DEBUG_DEVELOPER);;
        return;
    }

    // web request
    $url = get_config('crucible', 'steamfitterapiurl') . "/sessions/" . $id . "/dispatchtaskresults";
    //echo "GET $url<br>";

    $response = $client->get($url);
    if (!$response) {
        debugging("no response received by get_taskresults for $url", DEBUG_DEVELOPER);
        return;
    }
    if ($client->info['http_code']  !== 200) {
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }

    //echo "response:<br><pre>$response</pre>";
    if ($response === "[]") {
        return;
    }

    $r = json_decode($response);
    if (!$r) {
        debugging("could not decode json", DEBUG_DEVELOPER);
        return;
    }

    if ($client->info['http_code']  === 200) {
        return $r;
    } else {
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }
    return;
}

function endDate($a, $b) {
    return strnatcmp($a['endDate'], $b['endDate']);
}

function launchDate($a, $b) {
    return strnatcmp($a['launchDate'], $b['launchDate']);
}

function get_active_event($history) {
    if ($history == null) {
        return null;
    }
    foreach ($history as $odx) {
        if (($odx['status'] == "Active") || ($odx['status'] == "Creating") || ($odx['status'] == "Planning") ||($odx['status'] == "Applying") || ($odx['status'] == "Ending")) {
            return (object)$odx;
        }
    }
}

function get_token($client) {
    $access_token = $client->get_accesstoken();
    return $access_token->token;
}

function get_refresh_token($client) {
    $refresh_token = $client->get_refresh_token();
    return $refresh_token->token;
}

function get_scopes($client) {
    $access_token = $client->get_accesstoken();
    return $access_token->scope;
}

function get_clientid($client) {
    $issuer = $client->get_issuer();
    return $issuer->get('clientid');
}

function get_clientsecret($client) {
    $issuer = $client->get_issuer();
    return $issuer->get('clientsecret');
}

/**
 * @return array int => lang string the options for calculating the crucible grade
 *      from the individual attempt grades.
 */
function crucible_get_grading_options() {
    return array(
        CRUCIBLE_GRADEHIGHEST => get_string('gradehighest', 'crucible'),
        CRUCIBLE_GRADEAVERAGE => get_string('gradeaverage', 'crucible'),
        CRUCIBLE_ATTEMPTFIRST => get_string('attemptfirst', 'crucible'),
        CRUCIBLE_ATTEMPTLAST  => get_string('attemptlast', 'crucible')
    );
}

/**
 * @param int $option one of the values CRUCIBLE_GRADEHIGHEST, CRUCIBLE_GRADEAVERAGE,
 *      CRUCIBLE_ATTEMPTFIRST or CRUCIBLE_ATTEMPTLAST.
 * @return the lang string for that option.
 */
function crucible_get_grading_option_name($option) {
    $strings = crucible_get_grading_options();
    return $strings[$option];
}

function crucible_end($cm, $context, $crucible) {
    global $USER;
    $params = array(
        'objectid'      => $cm->id,
        'context'       => $context,
        'relateduserid' => $USER->id
    );
    $event = \mod_crucible\event\attempt_ended::create($params);
    $event->add_record_snapshot('crucible', $crucible);
    $event->trigger();
}

function crucible_start($cm, $context, $crucible) {
    global $USER;
    $params = array(
        'objectid'      => $cm->id,
        'context'       => $context,
        'relateduserid' => $USER->id
    );
    $event = \mod_crucible\event\attempt_started::create($params);
    $event->add_record_snapshot('crucible', $crucible);
    $event->trigger();
}

