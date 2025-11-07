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

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->dirroot/mod/crucible/lib.php");


/**
 * Initializes and returns an OAuth2 client authenticated as the system user.
 *
 * This function attempts to retrieve the issuer ID from the plugin configuration,
 * obtain the corresponding OAuth2 issuer, and establish a system-level client.
 * If any step fails, it logs debugging information and returns false.
 *
 * @return \core\oauth2\client|false The system OAuth2 client, or false on failure.
 */
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
        $client = false;
    }
    if ($client === false) {
        debugging('Cannot connect as system account', DEBUG_NORMAL);
        $details = 'Cannot connect as system account';
        // Throw new \Exception($details);
        return false;
    }
    return $client;
}

/**
 * Initializes and returns an OAuth2 client authenticated as the current user.
 *
 * Redirects to the OAuth2 login page if the user is not already authenticated.
 *
 * @return \core\oauth2\client|null The user OAuth2 client or null if setup fails.
 */
function setup() {
    global $PAGE;
    $issuerid = get_config('crucible', 'issuerid');
    if (!$issuerid) {
        throw new \moodle_exception('no issuer set for plugin');
    }
    $issuer = \core\oauth2\api::get_issuer($issuerid);

    $wantsurl = $PAGE->url;
    $returnparams = ['wantsurl' => $wantsurl, 'sesskey' => sesskey(), 'id' => $issuerid];
    $returnurl = new moodle_url('/auth/oauth2/login.php', $returnparams);

    $client = \core\oauth2\api::get_user_oauth_client($issuer, $returnurl);

    if ($client) {
        if (!$client->is_logged_in()) {
            // TODO this doesnt actually work.
            debugging('not logged in', DEBUG_DEVELOPER);
            redirect($client->get_login_url());
        }
    }

    return $client;
}

/**
 * Retrieves an event template from the Alloy API.
 *
 * @param object $client The authenticated OAuth2 client instance.
 * @param string $id The UUID of the event template to retrieve.
 * @return mixed The decoded event template object on success, or null on failure.
 */
function get_eventtemplate($client, $id) {

    if ($client == null) {
        throw new \moodle_exception('', '', '', null, 'Could not set up session');
    }

    // Web request.
    $url = get_config('crucible', 'alloyapiurl') . "/eventtemplates/" . $id;

    $response = $client->get($url);

    if ($client->info['http_code'] !== 200) {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        throw new \moodle_exception('', '', '', null,
            $client->info['http_code'] . " for $url " . $client->response['www-authenticate']
        );
    }

    if (!$response) {
        debugging('no response received by get_eventtemplate', DEBUG_DEVELOPER);
    }

    $r = json_decode($response);

    if (!$r) {
        debugging("could not find item by id", DEBUG_DEVELOPER);
        return;
    }

    return $r;
}

/**
 * Comparison function for sorting tasks by name.
 *
 * Used with usort to alphabetically sort task objects by their "name" property.
 *
 * @param object $a First task object.
 * @param object $b Second task object.
 * @return int Result of string comparison between task names.
 */
function tasksort($a, $b) {
    return strcmp($a->name, $b->name);
}

/**
 * Filters and sorts tasks based on visibility and gradability.
 *
 * This function filters a list of tasks by checking their visibility flag in the database,
 * and returns them sorted alphabetically by task name.
 *
 * @param array|null $tasks    Array of task objects retrieved from the API.
 * @param int        $visible  Visibility filter (0 = not visible, 1 = visible).
 * @param int        $gradable Gradability filter (currently unused).
 * @return array|null Filtered and sorted array of task objects, or null if $tasks is null.
 */
function filter_tasks($tasks, $visible = 0, $gradable = 0) {
    global $DB;
    if (is_null($tasks)) {
        return;
    }
    $filtered = [];
    foreach ($tasks as $task) {

        $rec = $DB->get_record_sql('SELECT * from {crucible_tasks} WHERE '
                . $DB->sql_compare_text('dispatchtaskid') . ' = '
                . $DB->sql_compare_text(':dispatchtaskid'), ['dispatchtaskid' => $task->id]);

        if ($rec === false) {
            // Do not display tasks we do not have in the db.
            // This will only show the tasks saved in the scenariotemplate.
            debugging('could not find task in db ' . $task->id, DEBUG_DEVELOPER);
            continue;
        }

        if ($visible === (int)$rec->visible ) {
            $task->points = $rec->points;
            $filtered[] = $task;
        }

        // TODO show automatic checks or show manual tasks only?
        // if ($task->triggerCondition == "Manual") {
        // $filtered[] = $task;
        // }
        // $filtered[] = $task;
    }
    // Sort the array by name.
    usort($filtered, "tasksort");
    return $filtered;
}

/**
 * Retrieves all event templates from the Alloy API.
 *
 * Makes an authenticated GET request to the Alloy API to fetch the list of available event templates.
 *
 * @param object $client The authenticated OAuth2 client object.
 * @return mixed|null Decoded JSON response as an object or array on success, null on failure.
 */
function get_eventtemplates($client) {

    if ($client == null) {
        debugging('error with client in get_eventtemplates', DEBUG_DEVELOPER);
        return;
    }

    // Web request.
    $url = get_config('crucible', 'alloyapiurl') . "/eventtemplates";
    // echo "GET $url<br>";

    $response = $client->get($url);
    if (!$response) {
        debugging("no response received by get_eventtemplates for $url", DEBUG_DEVELOPER);
    }
    // echo "response:<br><pre>$response</pre>";
    if ($client->info['http_code'] !== 200) {
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

/**
 * Initiates a new event based on the given event template ID via the Alloy API.
 *
 * Sends a POST request to create a new event instance using the specified event template ID.
 *
 * @param object $client The authenticated OAuth2 client.
 * @param string $id The ID of the event template to start.
 * @return mixed|null The new event ID on success, null on failure.
 */
function start_event($client, $id) {

    if ($client == null) {
        debugging('error with client in start_event', DEBUG_DEVELOPER);;
        return;
    }

    // Web request.
    $url = get_config('crucible', 'alloyapiurl') . "/eventtemplates/" . $id . "/events";
    // echo "POST $url<br>";

    $response = $client->post($url);
    if (!$response) {
        debugging('no response received by start_event response code ' , $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        return;
    }
    // echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        // echo "could not decode json<br>";
        return;
    }

    // Success.
    if ($client->info['http_code'] === 201) {
        return $r->id;
    }
    if ($client->info['http_code'] === 500) {
        // echo "response code ". $client->info['http_code'] . "<br>";
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
        throw new moodle_exception('unexpectederror', 'error', '', null, $r->detail);
    }

    return;
}

/**
 * Stops a running event in Alloy by calling the API endpoint.
 *
 * @param object $client The authenticated HTTP client used to perform the request.
 * @param string $id The ID of the event to stop.
 * @return void
 */
function stop_event($client, $id) {

    if ($client == null) {
        debugging('error with client in stop_event', DEBUG_DEVELOPER);;
        return;
    }

    // Web request.
    $url = get_config('crucible', 'alloyapiurl') . "/events/" . $id . "/end";
    // echo "DELETE $url<br>";

    $response = $client->delete($url);

    if ($client->info['http_code'] !== 204) {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
    }

    // if (!$response) {
    // throw new \Exception($response);
    // return;
    // }
    // echo "response:<br><pre>$response</pre>";
    return;
}

/**
 * Executes a task in Steamfitter by calling the appropriate API endpoint.
 *
 * @param object $client The authenticated HTTP client used to perform the request.
 * @param string $id The ID of the task to execute.
 * @return mixed The decoded JSON response from the API, or null on failure.
 */
function run_task($client, $id) {

    if ($client == null) {
        return;
    }

    // Web request.
    $url = get_config('crucible', 'steamfitterapiurl') . "/tasks/" . $id . "/execute";

    $response = $client->post($url);

    if ($client->info['http_code'] !== 200) {
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }

    $r = json_decode($response);

    return $r;
}

/**
 * Extends the duration of an event by sending a PATCH request to the Alloy API.
 *
 * @param object $client The authenticated HTTP client used to perform the request.
 * @param object $data The event data containing the updated values.
 * @return mixed The decoded JSON response from the API, or null on failure.
 */
function extend_event($client, $data) {

    if ($client == null) {
        return;
    }

    // Web request.
    $url = get_config('crucible', 'alloyapiurl') . "/events/" . $data->id;
    $client->setHeader('Content-Type: application/json-patch+json');

    $response = $client->put($url, json_encode($data));

    if ($client->info['http_code'] !== 200) {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        return;
    }

    $r = json_decode($response);

    return $r;
}

/**
 * Retrieves a specific event from the Alloy API.
 *
 * @param object $client The authenticated HTTP client used to make the request.
 * @param string $id The ID of the event to retrieve.
 * @return mixed The decoded event object if successful, or null on failure.
 */
function get_event($client, $id) {

    if ($client == null) {
        debugging('error with client in get_event', DEBUG_DEVELOPER);;
        return;
    }

    if ($id == null) {
        debugging('error with id in get_event', DEBUG_DEVELOPER);;
        return;
    }

    // Web request.
    $url = get_config('crucible', 'alloyapiurl') . "/events/" . $id;
    // echo "GET $url<br>";

    $response = $client->get($url);
    if (!$response) {
        debugging("no response received by get_event for $url", DEBUG_DEVELOPER);
        return;
    }
    // echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        debugging("could not decode json for $url", DEBUG_DEVELOPER);
        return;
    }

    if ($client->info['http_code'] === 200) {
        return $r;
    } else {
        debugging('response code ' . $client->info['http_code'] . " for $url", DEBUG_DEVELOPER);
        throw new \moodle_exception($r->detail);
    }
    return;
}

/**
 * Retrieves the tasks associated with a specific scenario template.
 *
 * @param object $client The authenticated OAuth2 client.
 * @param string $id The GUID of the scenario template.
 * @return array|null Decoded task objects if successful, null otherwise.
 */
function get_scenariotemplatetasks($client, $id) {

    if ($client == null) {
        debugging('error with client in get_scenariotemplatetasks', DEBUG_DEVELOPER);;
        return;
    }

    if ($id == null) {
        debugging('error with id in get_scenariotemplatetasks', DEBUG_DEVELOPER);;
        return;
    }

    // Web request.
    $url = get_config('crucible', 'steamfitterapiurl') . "/scenariotemplates/" . $id . "/tasks";
    // echo "GET $url<br>";

    $response = $client->get($url);
    if (!$response) {
        debugging('no response received by get_scenariotemplatetasks', DEBUG_DEVELOPER);
        return;
    }
    // echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        debugging("could not decode json", DEBUG_DEVELOPER);
        return;
    }

    if ($client->info['http_code'] === 200) {
        return $r;
    } else {
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }
    return;
}

/**
 * Retrieves a scenario object by its ID from the SteamFitter API.
 *
 * @param object $client The authenticated OAuth2 client used to make the API request.
 * @param string $id The unique identifier of the scenario to retrieve.
 * @return object|null The decoded scenario object on success, or null on failure.
 */
function get_scenario($client, $id) {

    if ($client == null) {
        debugging('error with client in get_scenario', DEBUG_DEVELOPER);;
        return;
    }

    if ($id == null) {
        debugging('error with id in get_scenario', DEBUG_DEVELOPER);;
        return;
    }

    // Web request.
    $url = get_config('crucible', 'steamfitterapiurl') . "/scenarios/" . $id;
    // echo "GET $url<br>";

    $response = $client->get($url);
    if (!$response) {
        debugging('no response received by get_scenario', DEBUG_DEVELOPER);
        return;
    }
    // echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        debugging("could not decode json", DEBUG_DEVELOPER);
        return;
    }

    if ($client->info['http_code'] === 200) {
        return $r;
    } else {
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }
    return;
}

/**
 * Retrieves the list of tasks for a specific scenario from the SteamFitter API.
 *
 * @param object $client The authenticated OAuth2 client used to make the API request.
 * @param string $id The unique identifier of the scenario.
 * @return array|null An array of task objects on success, or null on failure.
 */
function get_scenariotasks($client, $id) {

    if ($client == null) {
        debugging('error with client in get_scenariotasks', DEBUG_DEVELOPER);;
        return;
    }

    if ($id == null) {
        debugging('error with id in get_scenariotasks', DEBUG_DEVELOPER);;
        return;
    }

    // Web request.
    $url = get_config('crucible', 'steamfitterapiurl') . "/scenarios/" . $id . "/tasks";
    // echo "GET $url<br>";

    $response = $client->get($url);
    if (!$response) {
        debugging('no response received by get_scenariotasks', DEBUG_DEVELOPER);
        return;
    }
    // echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        debugging("could not decode json", DEBUG_DEVELOPER);
        return;
    }

    if ($client->info['http_code'] === 200) {
        return $r;
    } else {
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }
    return;
}

/**
 * Retrieves a single task by its ID from the SteamFitter API.
 *
 * @param object $client The authenticated OAuth2 client used to perform the request.
 * @param string $id The unique identifier of the task.
 * @return object|null The decoded task object if found, or null on failure.
 */
function get_task($client, $id) {

    if ($client == null) {
        debugging('error with client in get_tasks', DEBUG_DEVELOPER);;
        return;
    }

    if ($id == null) {
        debugging('error with id in get_tasks', DEBUG_DEVELOPER);;
        return;
    }

    // Web request.
    $url = get_config('crucible', 'steamfitterapiurl') . "/tasks/" . $id;
    // echo "GET $url<br>";

    $response = $client->get($url);
    if (!$response) {
        debugging('no response received by get_tasks', DEBUG_DEVELOPER);
        return;
    }
    // echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        debugging("could not decode json", DEBUG_DEVELOPER);
        return;
    }

    if ($client->info['http_code'] === 200) {
        return $r;
    } else {
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }
    return;
}

/**
 * Retrieves task results for a specific scenario from the SteamFitter API.
 *
 * @param object $client The authenticated OAuth2 client.
 * @param string $id The ID of the scenario for which to retrieve task results.
 * @return array|null An array of task result objects, or null on failure.
 */
function get_taskresults($client, $id) {

    if ($client == null) {
        debugging('error with client in get_taskresults', DEBUG_DEVELOPER);;
        return;
    }

    if ($id == null) {
        debugging('error with client in get_taskresults', DEBUG_DEVELOPER);;
        return;
    }

    // Web request.
    $url = get_config('crucible', 'steamfitterapiurl') . "/scenarios/" . $id . "/results";
    // echo "GET $url<br>";

    $response = $client->get($url);
    if (!$response) {
        debugging("no response received by get_taskresults for $url", DEBUG_DEVELOPER);
        return;
    }
    if ($client->info['http_code'] !== 200) {
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }

    // echo "response:<br><pre>$response</pre>";
    if ($response === "[]") {
        return;
    }

    $r = json_decode($response);
    if (!$r) {
        debugging("could not decode json", DEBUG_DEVELOPER);
        return;
    }

    if ($client->info['http_code'] === 200) {
        return $r;
    } else {
        debugging('response code ' . $client->info['http_code'], DEBUG_DEVELOPER);
    }
    return;
}

/**
 * Comparison function for sorting by end date.
 *
 * @param array $a First item to compare (must contain 'endDate' key).
 * @param array $b Second item to compare (must contain 'endDate' key).
 * @return int Comparison result: -1, 0, or 1.
 */
function end_date($a, $b) {
    $aDate = isset($a['endDate']) ? (string)$a['endDate'] : '';
    $bDate = isset($b['endDate']) ? (string)$b['endDate'] : '';
    return strnatcmp($aDate, $bDate);
}

/**
 * Comparison function for sorting by launch date.
 *
 * @param array $a First item to compare (must contain 'launchDate' key).
 * @param array $b Second item to compare (must contain 'launchDate' key).
 * @return int Comparison result: -1, 0, or 1.
 */
function launchDate($a, $b) {
    $aDate = isset($a['launchDate']) ? (string)$a['launchDate'] : '';
    $bDate = isset($b['launchDate']) ? (string)$b['launchDate'] : '';
    return strnatcmp($aDate, $bDate);
}

/**
 * Filters and returns active events from a given event history array.
 *
 * An event is considered active if its status is one of the following:
 * "Active", "Creating", "Planning", "Applying", or "Ending".
 *
 * @param array|null $history Array of event data, each containing a 'status' key.
 * @return array|null List of active events as objects, or null if input is null.
 */
function get_active_events($history) {
    if ($history == null) {
        return null;
    }

    $activeevents = [];

    foreach ($history as $odx) {
        if (($odx['status'] == "Active") || ($odx['status'] == "Creating") ||
            ($odx['status'] == "Planning") ||($odx['status'] == "Applying") || ($odx['status'] == "Ending")) {
            array_push($activeevents, (object)$odx);
        }
    }

    return $activeevents;
}

/**
 * Retrieves the OAuth2 access token from the given client.
 *
 * @param object $client The authenticated OAuth2 client instance.
 * @return string|null The access token string if available, or null on failure.
 */
function get_token($client) {
    $accesstoken = $client->get_accesstoken();
    return $accesstoken->token;
}

/**
 * Retrieves the OAuth2 refresh token from the given client.
 *
 * @param object $client The authenticated OAuth2 client instance.
 * @return string|null The refresh token string if available, or null on failure.
 */
function get_refresh_token($client) {
    $refreshtoken = $client->get_refresh_token();
    return $refreshtoken->token;
}

/**
 * Retrieves the scopes associated with the current OAuth2 access token.
 *
 * @param object $client The authenticated OAuth2 client instance.
 * @return string|null The scope string if available, or null on failure.
 */
function get_scopes($client) {
    $accesstoken = $client->get_accesstoken();
    return $accesstoken->scope;
}

/**
 * Retrieves the client ID from the given OAuth2 client instance.
 *
 * @param object $client The authenticated OAuth2 client instance.
 * @return string|null The client ID if available, or null on failure.
 */
function get_clientid($client) {
    $issuer = $client->get_issuer();
    return $issuer->get('clientid');
}

/**
 * Retrieves the client secret from the given OAuth2 client instance.
 *
 * @param object $client The authenticated OAuth2 client instance.
 * @return string|null The client secret if available, or null on failure.
 */
function get_clientsecret($client) {
    $issuer = $client->get_issuer();
    return $issuer->get('clientsecret');
}

/**
 * Returns the available grading options for the Crucible activity.
 *
 * @return array Array of grading option constants mapped to their language string names.
 */
function crucible_get_grading_options() {
    return [
        CRUCIBLE_GRADEHIGHEST => get_string('gradehighest', 'crucible'),
        CRUCIBLE_GRADEAVERAGE => get_string('gradeaverage', 'crucible'),
        CRUCIBLE_ATTEMPTFIRST => get_string('attemptfirst', 'crucible'),
        CRUCIBLE_ATTEMPTLAST  => get_string('attemptlast', 'crucible'),
    ];
}

/**
 * Returns the language string for a given Crucible grading option.
 *
 * @param int $option One of the CRUCIBLE_GRADE* constants.
 * @return string The localized name of the grading option.
 */
function crucible_get_grading_option_name($option) {
    $strings = crucible_get_grading_options();
    return $strings[$option];
}

/**
 * Triggers the 'attempt_ended' event for a Crucible activity.
 *
 * @param cm_info $cm Course module object.
 * @param context_module $context Context of the module.
 * @param stdClass $crucible Crucible activity instance.
 */
function crucible_end($cm, $context, $crucible) {
    global $USER;
    $params = [
        'objectid'      => $cm->id,
        'context'       => $context,
        'relateduserid' => $USER->id,
    ];
    $event = \mod_crucible\event\attempt_ended::create($params);
    $event->add_record_snapshot('crucible', $crucible);
    $event->trigger();
}

/**
 * Triggers the 'attempt_started' event for a Crucible activity.
 *
 * @param cm_info $cm Course module object.
 * @param context_module $context Context of the module.
 * @param stdClass $crucible Crucible activity instance.
 */
function crucible_start($cm, $context, $crucible) {
    global $USER;
    $params = [
        'objectid'      => $cm->id,
        'context'       => $context,
        'relateduserid' => $USER->id,
    ];
    $event = \mod_crucible\event\attempt_started::create($params);
    $event->add_record_snapshot('crucible', $crucible);
    $event->trigger();
}

/**
 * Retrieves all virtual machines associated with a given view ID using system authentication.
 *
 * @param object $systemauth Authenticated client object for system-level OAuth.
 * @param string $id The ID of the view to retrieve VMs from.
 * @return mixed Decoded JSON object of virtual machines, or null on failure.
 */
function get_allvms($systemauth, $id) {
    if ($systemauth == null) {
        echo 'error with systemauth<br>';
        return;
    }

    if ($id == null) {
        echo 'error with id<br>';
        return;
    }

    // Web request.
    $url = "https://s3vm.cyberforce.site/api/views/" . $id . "/vms";
    // echo "GET $url<br>";

    $response = $systemauth->get($url);
    if (!$response) {
        debugging("no response received by get_allvms $url", DEBUG_DEVELOPER);
        return;
    }
    if ($systemauth->info['http_code'] !== 200) {
        debugging('response code ' . $systemauth->info['http_code'], DEBUG_DEVELOPER);
    }
    // echo "response:<br><pre>$response</pre>";
    if ($response === "[]") {
        return;
    }

    $r = json_decode($response);
    if (!$r) {
        debugging("could not decode json", DEBUG_DEVELOPER);
        return;
    }
    return $r;
}

/**
 * Creates and executes a custom task via the Steamfitter API.
 *
 * @param object $systemauth Authenticated system-level OAuth client.
 * @param mixed $data The data payload (typically an object or array) to define the task.
 * @return mixed The decoded JSON response from the API, or null on failure.
 */
function create_and_exec_task($systemauth, $data) {
    if ($systemauth == null) {
        return;
    }

    // Web request.
    $url = get_config('crucible', 'steamfitterapiurl') . "/tasks/execute";
    $systemauth->setHeader('Content-Type: application/json');

    $response = $systemauth->post($url, $data);
    if ($systemauth->info['http_code'] !== 200) {
        debugging('response code ' . $systemauth->info['http_code'] . " on $url", DEBUG_DEVELOPER);
        return;
    }

    $r = json_decode($response);
    if (is_null($r)) {
        debugging("could not decode json response", DEBUG_DEVELOPER);
        return;
    }

    debugging("successful task execution", DEBUG_DEVELOPER);
    return $r;
}

/**
 * Retrieves all crucible attempts for a given course.
 *
 * @param int $course The ID of the course to fetch attempts for.
 * @return array Array of \mod_crucible\crucible_attempt objects.
 */
function getall_course_attempts($course) {
    global $DB, $USER;

    $sqlparams = [];
    $where = [];

    $where[] = '{crucible}.course= ?';
    $sqlparams[] = $course;

    $wherestring = implode(' AND ', $where);

    $sql = "SELECT {crucible_attempts}.* FROM {crucible_attempts} JOIN {crucible}
            ON {crucible_attempts}.crucibleid = {crucible}.id WHERE $wherestring";
    $dbattempts = $DB->get_records_sql($sql, $sqlparams);

    $attempts = [];
    // Create array of class attempts from the db entry.
    foreach ($dbattempts as $dbattempt) {
        $attempts[] = new \mod_crucible\crucible_attempt($dbattempt);
    }

    return $attempts;

}

/**
 * Retrieves all crucible attempts across all courses.
 *
 * @param int $course Unused parameter (included for consistency or future use).
 * @return array Array of \mod_crucible\crucible_attempt objects.
 */
function getall_crucible_attempts($course) {
    global $DB, $USER;

    $sql = "SELECT * FROM {crucible_attempts}";
    $dbattempts = $DB->get_records_sql($sql);

    $attempts = [];
    // Create array of class attempts from the db entry.
    foreach ($dbattempts as $dbattempt) {
        $attempts[] = new \mod_crucible\crucible_attempt($dbattempt);
    }

    return $attempts;

}

/**
 * Ensures the current user is added to the crucible_attempt_users table
 * for each attempt associated with the given events, if not already present.
 *
 * @param array $events Array of event objects that contain crucible attempts.
 * @return void
 */
function ensure_added_to_event_attempts($events) {
    global $DB, $USER;

    foreach ($events as $event) {
        $sqlparams = [];
        $where = [];

        $where[] = '{crucible_attempts}.eventid = ?';
        $sqlparams[] = $event->id;

        $wherestring = implode(' AND ', $where);

        $sql = "SELECT {crucible_attempts}.* FROM {crucible_attempts} WHERE $wherestring";

        $dbattempts = $DB->get_records_sql($sql, $sqlparams);

        $attempts = [];

        // Create array of class attempts from the db entry.
        foreach ($dbattempts as $dbattempt) {
            $attempts[] = new \mod_crucible\crucible_attempt($dbattempt);
        }

        foreach ($attempts as $attempt) {
            if ($attempt->userid == $USER->id) {
                continue;
            }

            $sqlparams = [];
            $where = [];

            $where[] = '{crucible_attempt_users}.attemptid = ?';
            $sqlparams[] = $attempt->id;

            $where[] = '{crucible_attempt_users}.userid = ?';
            $sqlparams[] = $USER->id;

            $wherestring = implode(' AND ', $where);
            $sql = "SELECT * FROM {crucible_attempt_users} WHERE $wherestring";

            $dbattemptusers = $DB->get_records_sql($sql, $sqlparams);

            // Add user to attempt if not already joined.
            if (empty($dbattemptusers)) {
                $attemptuser = new stdClass();
                $attemptuser->attemptid = $attempt->id;
                $attemptuser->userid = $USER->id;

		$DB->insert_record('crucible_attempt_users', $attemptuser);
		debugging("added " . $USER->username . " to attempt " . $attempt->id . " for event " . $attempt->eventid, DEBUG_DEVELOPER);
            }
        }
    }
}
