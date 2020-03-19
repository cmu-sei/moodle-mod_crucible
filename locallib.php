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
    //echo "<br><br><br><br>setup_system<br>";

    $issuerid = get_config('crucible', 'issuerid');
    if (!$issuerid) {
        echo "crucible does not have issuerid set<br>";
        return;
    }
    $issuer = \core\oauth2\api::get_issuer($issuerid);

    try {
        $systemauth = \core\oauth2\api::get_system_oauth_client($issuer);
    } catch (Exception $e) {
        echo "$e->errorcode<br>";
        var_dump($e);
    }
    if ($systemauth === false) {
        $details = 'Cannot connect as system account';
        echo "$details<br>";
        throw new \Exception($details);
        return;
    }
    //echo "system looks good<Br>";
    return $systemauth;
}

function get_token_url() {
    $config = get_config('crucible');
    $issuerid = $config->issuerid;
    $issuer = \core\oauth2\api::get_issuer($issuerid);
    return $issuer->get_endpoint_url('token');
}

function setup() {
    //echo "setup<br>";
    global $PAGE;
    $issuerid = get_config('crucible', 'issuerid');
    if (!$issuerid) {
            //echo "crucible does not have issuerid set<br>";
        return;
    }
    $issuer = \core\oauth2\api::get_issuer($issuerid);

    $wantsurl = $PAGE->url;
    $returnparams = ['wantsurl' => $wantsurl, 'sesskey' => sesskey(), 'id' => $issuerid];
    $returnurl = new moodle_url('/auth/oauth2/login.php', $returnparams);

    $client = \core\oauth2\api::get_user_oauth_client($issuer, $returnurl);

    if ($client) {
        //echo "we got client<Br>";
        //var_dump($client);
        //TODO store and save token or just use js everywhere...
        //$token = $client->get_stored_token();
        //if ($token) {
            //echo  "we got token<br>";
            //var_dump($token); exit;
        //} else {
            //echo "no stored token<br>";
        //}
        if (!$client->is_logged_in()) {
            //echo "not logged in, lets redurect to id server<br>";
            //echo "login url " . $client->get_login_url();
            redirect($client->get_login_url());
        } else {
            //echo "user already logged in<br>";
        }
        //$auth = new \auth_oauth2\auth();
        //$auth->complete_login($client, $wantsurl);
    } else {
        //echo  "no client<br>";
        //throw new moodle_exception('Could not get an OAuth client.');
    }

    //return $systemauth;
    return $client;
}

function get_definition($id) {
    //try {
    //    $systemauth = setup_system();
    //} catch (Exception $e) {
        //echo "system failed, try user<Br>";
        $systemauth = setup();
    //}

    if ($systemauth == null) {
        //echo 'error with systemauth<br>';
        return;
    }

    // web request
    $url = get_config('crucible', 'alloyapiurl') . "/definitions/" . $id;
    //echo "GET $url<br>";

    $response = $systemauth->get($url);
    if (!$response) {
        echo "curl error: " . curl_strerror($systemauth->errno) . "<br>";
        echo "check refresh token for the account<br>";
        //throw new \Exception($response);
        //return;
    }
    //echo "response:<br><pre>$response</pre>";
    if ($systemauth->info['http_code']  !== 200) {
        echo "response code ". $systemauth->info['http_code'] . "<br>";
        //throw new \Exception($response);
        return;
    }
    $r = json_decode($response);

    if (!$r) {
        echo "could not find item by id<br>";
        return;
    }

    return $r;
}

function get_definitions() {

    //try {
        //echo "<br><br><br><br><br>trying system now<br>";
    //    $systemauth = setup_system();
    //} catch (Exception $e) {
        // try user
        //echo "trying user now<br>";
        $systemauth = setup();
    //}

    if ($systemauth == null) {
        echo 'error with systemauth<br>';
        return;
    }

    // web request
    $url = get_config('crucible', 'alloyapiurl') . "/definitions";
    //echo "GET $url<br>";

    $response = $systemauth->get($url);
    if (!$response) {
        echo "curl error: " . curl_strerror($systemauth->errno) . "<br>";
        //throw new \Exception($response);
        //return;
    }
    //echo "response:<br><pre>$response</pre>";
    if ($systemauth->info['http_code']  !== 200) {
        echo "response code ". $systemauth->info['http_code'] . "<br>";
        //throw new \Exception($response);
        return;
    }
    $r = json_decode($response);

    if (!$r) {
        echo "could not find item by id<br>";
        return;
    }
    return $r;
}

function start_implementation($systemauth, $id) {

    if ($systemauth == null) {
        echo 'error with systemauth<br>';
        return;
    }

    // web request
    $url = get_config('crucible', 'alloyapiurl') . "/definitions/" . $id . "/implementations";
    //echo "POST $url<br>";

    $response = $systemauth->post($url);
    if (!$response) {
        echo "curl error: " . curl_strerror($systemauth->errno) . "<br>";
        echo "response code ". $systemauth->info['http_code'] . "<br>";
        //throw new \Exception($response);
        return;
    }
    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        //echo "could not decode json<br>";
        return;
    }

    // success
    if ($systemauth->info['http_code']  === 201) {
        return $r->id;
    }

    if ($systemauth->info['http_code']  === 500) {
        //echo "response code ". $systemauth->info['http_code'] . "<br>";
        echo "$r->detail<br>";
        //throw new \Exception($r);
        return;
    }

    return;
}


function stop_implementation($systemauth, $id) {

    if ($systemauth == null) {
        echo 'error with systemauth<br>';
        return;
    }

    // web request
    $url = get_config('crucible', 'alloyapiurl') . "/implementations/" . $id . "/end";
    //echo "DELETE $url<br>";

    $response = $systemauth->delete($url);

    if ($systemauth->info['http_code']  !== 204) {
        echo "response code ". $systemauth->info['http_code'] . "<br>";
        throw new \Exception($response);
    }

    //if (!$response) {
        //throw new \Exception($response);
    //    return;
    //}
    //echo "response:<br><pre>$response</pre>";
    return;
}

function get_implementation($systemauth, $id) {

    if ($systemauth == null) {
        echo 'error with systemauth<br>';
        return;
    }

    if ($id == null) {
        echo 'error with id<br>';
        return;
    }

    // web request
    $url = get_config('crucible', 'alloyapiurl') . "/implementations/" . $id;
    //echo "GET $url<br>";

    $response = $systemauth->get($url);
    if (!$response) {
        echo "curl error: " . curl_strerror($systemauth->errno) . "<br>";
        //throw new \Exception($response);
        return;
    }
    //echo "response:<br><pre>$response</pre>";
    $r = json_decode($response);
    if (!$r) {
        echo "could not decode json<br>";
        return;
    }

    if ($systemauth->info['http_code']  === 200) {
        return $r;
    } else {
        echo "response code ". $systemauth->info['http_code'] . "<br>";
        //throw new \Exception($response);
    }
    return;
}


// GET /definitions/{definitionId}/implementations/mine -- Gets the user's Implementations for the indicated Definition
function list_implementations($systemauth, $id) {

    if ($systemauth == null) {
        echo 'error with systemauth<br>';
        return;
    }

    // web request
    $url = get_config('crucible', 'alloyapiurl') . "/definitions/" . $id . "/implementations/mine";
    //echo "GET $url<br>";

    $response = $systemauth->get($url);
    if (!$response) {
        echo "curl error: " . curl_strerror($systemauth->errno) . "<br>";
        //throw new \Exception($response);
        return;
    }
    //echo "response:<br><pre>$response</pre>";
    if ($systemauth->info['http_code']  !== 200) {
        echo "response code ". $systemauth->info['http_code'] . "<br>";
        //throw new \Exception($response);
        return;
    }
    $r = json_decode($response, true);

    if (!$r) {
        echo "could not end<br>";
        return;
    }
    usort($r, 'launchDate');
    return $r;
}

function endDate($a, $b) {
    return strnatcmp($a['endDate'], $b['endDate']);
}

function launchDate($a, $b) {
    return strnatcmp($a['launchDate'], $b['launchDate']);
}

function get_launched($history) {
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

