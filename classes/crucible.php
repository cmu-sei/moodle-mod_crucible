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

namespace mod_crucible;

defined('MOODLE_INTERNAL') || die();

/**
 * crucible Attempt wrapper class to encapsulate functions needed to individual
 * attempt records
 *
 * @package     mod_crucible
 * @copyright   2020 Carnegie Mellon Univeristy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

class crucible {

    public $event;

    public $crucible;

    public $openAttempt;

    public $eventtemplate;

    /**
     * Construct class
     *
     * @param object $cm The course module instance
     * @param object $course The course object the activity is contained in
     * @param object $quiz The specific real time quiz record for this activity
     * @param \moodle_url $pageurl The page url
     * @param array  $pagevars The variables and options for the page
     * @param string $renderer_subtype Renderer sub-type to load if requested
     *
     */
    public function __construct($cm, $course, $crucible, $pageurl, $pagevars = array(), $renderer_subtype = null) {
        global $CFG, $PAGE;

        $this->cm = $cm;
        $this->course = $course;
        $this->crucible = $crucible;
        $this->pagevars = $pagevars;

        $this->context = \context_module::instance($cm->id);
        $PAGE->set_context($this->context);

        $this->systemauth = setup();

        $this->renderer = $PAGE->get_renderer('mod_crucible', $renderer_subtype);

        //$this->renderer->init($this, $pageurl, $pagevars);
    }

    // GET /definitions/{eventtemplateId}/implementations/mine -- Gets the user's Implementations for the indicated Definition
    function list_events() {

        if ($this->systemauth == null) {
            echo 'error with systemauth<br>';
            return;
        }

        // web request
        $url = get_config('crucible', 'alloyapiurl') . "/definitions/" . $this->crucible->eventtemplateid . "/implementations/mine";
        //echo "GET $url<br>";

        $response = $this->systemauth->get($url);

        if ($this->systemauth->info['http_code']  !== 200) {
            echo "response code ". $this->systemauth->info['http_code'] . "<br>";
            return;
        }

        //echo "response:<br><pre>$response</pre>";
        if (!$response) {
            echo "curl error: " . curl_strerror($this->systemauth->errno) . "<br>";
            debugging('no response received by list_events', DEBUG_DEVELOPER);
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


    public function get_open_attempt() {
        $attempts = $this->getall_attempts('open');
        if (count($attempts) !== 1) {
            return false;
        } else {
            $this->openAttempt = reset($attempts);
            // update values
            if ($this->openAttempt->eventid === null) {
                $this->openAttempt->eventid = $this->event->id;
            }
            if ($this->openAttempt->sessionid === null) {
                $this->openAttempt->sessionid = $this->event->sessionId;
            }
            //TODO remove check for Z once API is updated
            if (strpos($this->event->expirationDate, "Z")) {
                $this->openAttempt->endtime = strtotime($this->event->expirationDate);
            } else {
                $this->openAttempt->endtime = strtotime($this->event->expirationDate . 'Z');
            }
            $this->openAttempt->save();
            return true;
        }
    }

    public function getall_attempts($state = 'all') {
        global $DB;

        $sqlparams = array();
        $where = array();

        $where[] = 'crucibleid = ?';
        $sqlparams[] = $this->crucible->id;

        switch ($state) {
            case 'open':
                $where[] = 'state = ?';
                $sqlparams[] = crucible_attempt::INPROGRESS;
                break;
            case 'closed':
                $where[] = 'state = ?';
                $sqlparams[] = crucible_attempt::FINISHED;
                break;
            default:
                // add no condition for state when 'all' or something other than open/closed
        }

        $wherestring = implode(' AND ', $where);

        $sql = "SELECT * FROM {crucible_attempts} WHERE $wherestring";
        $dbattempts = $DB->get_records_sql($sql, $sqlparams);

        $attempts = array();
        // create array of class attempts from the db entry
        foreach ($dbattempts as $dbattempt) {
            $attempts[] = new crucible_attempt($dbattempt);
        }
        return $attempts;

    }

    public function init_attempt() {
        global $DB, $USER;
        $openAttempt = $this->get_open_attempt();
        if ($openAttempt !== false) {
            $this->openAttempt = $openAttempt;
            // update values
            if ($this->openAttempt->eventid === null) {
                $this->openAttempt->eventid = $this->event->id;
            }
            if ($this->openAttempt->sessionid === null) {
                $this->openAttempt->sessionid = $this->event->sessionId;
            }
            //TODO remove check for Z once API is updated
            if (strpos($this->event->expirationDate, "Z")) {
                $this->openAttempt->endtime = strtotime($this->event->expirationDate);
            } else {
                $this->openAttempt->endtime = strtotime($this->event->expirationDate . 'Z');
            }
            if ($this->openAttempt->save()) {
                    return true;
            } else {
                    return false;
            }
        }

        // create a new attempt
        $attempt = new \mod_crucible\crucible_attempt();
        $attempt->userid = $USER->id;
        $attempt->state = \mod_crucible\crucible_attempt::NOTSTARTED;
        $attempt->timemodified = time();
        $attempt->timestart = time();
        $attempt->timefinish = null;
        $attempt->crucibleid = $this->crucible->id;
        $attempt->setState('inprogress');
        $attempt->score = 0;
        if ($this->event->id) {
            $attempt->eventid = $this->event->id;
        } else {
            $attempt->event->id = 0;
        }
        if ($this->event->sessionId) {
            $attempt->sessionid = 0;
        }
        //TODO remove check for Z once API is updated
        if (strpos($this->event->expirationDate, "Z")) {
            $attempt->endtime = strtotime($this->event->expirationDate);
        } else {
            $attempt->endtime = strtotime($this->event->expirationDate . 'Z');
        }

        // TODO get list of tasks
        $attempt->tasks = "";

        if ($attempt->save()) {
            $this->openAttempt = $attempt;
        } else {
            return false;
        }

        //TODO call start attempt event class from here
        return true;
    }
}
