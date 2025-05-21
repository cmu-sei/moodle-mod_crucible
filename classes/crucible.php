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

    public $events;

    public $crucible;

    public $openAttempt;

    public $eventtemplate;

    protected $context;

    protected $isinstructor;

    public $systemauth;

    public $userauth;

    public $cm;

    public $course;

    public $pagevars;

    public $renderer;

    /**
     * Construct class
     *
     * @param object $cm The course module instance
     * @param object $course The course object the activity is contained in
     * @param object $crucible The specific crucible record for this activity
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

        $this->is_instructor();

        $this->systemauth = setup_system();
        $this->userauth = setup(); //fails when called by runtask

        $this->renderer = $PAGE->get_renderer('mod_crucible', $renderer_subtype);
    }


    /**
     * Wrapper for the has_capability function to provide the context
     *
     * @param string $capability
     * @param int    $userid
     *
     * @return bool Whether or not the current user has the capability
     */
    public function has_capability($capability, $userid = 0) {
        if ($userid !== 0) {
            // pass in userid if there is one
            return has_capability($capability, $this->context, $userid);
        } else {
            // just do standard check with current user
            return has_capability($capability, $this->context);
        }
    }

    /**
     * Quick function for whether or not the current user is the instructor/can control the quiz
     *
     * @return bool
     */
    public function is_instructor() {

        if (is_null($this->isinstructor)) {
            $this->isinstructor = $this->has_capability('mod/crucible:manage');
            return $this->isinstructor;
        } else {
            return $this->isinstructor;
        }
    }

    // GET /eventtemplates/{eventtemplateId}/events/mine -- Gets the user's Implementations for the indicated Definition
    function list_events() {

        if ($this->userauth == null) {
            echo 'error with userauth<br>';
            return;
        }

        // web request
        $url = get_config('crucible', 'alloyapiurl') . "/eventtemplates/" . $this->crucible->eventtemplateid . "/events/mine?includeInvites=true";
        //echo "GET $url<br>";

        $response = $this->userauth->get($url);

        if ($this->userauth->info['http_code']  !== 200) {
            debugging('response code ' . $this->userauth->info['http_code'] . " $url", DEBUG_DEVELOPER);
            return;
        }

        //echo "response:<br><pre>$response</pre>";
        if (!$response) {
            debugging("no response received by list_events $url", DEBUG_DEVELOPER);
            return;
        }

        $r = json_decode($response, true);

	if (!$r) {
	    // this can mean the user made 0 attempts at this event
            debugging("could not decode json $url", DEBUG_DEVELOPER);
            return;
        }

        usort($r, 'launchDate');
        return $r;
    }

    public function get_attempt($attemptid) {
        global $DB;

        $dbattempt = $DB->get_record('crucible_attempts', array("id" => $attemptid));

        return new crucible_attempt($dbattempt);
    }


    public function get_open_attempt($attemptid) {
        global $USER;

        $attempts = $this->getall_attempts('open');

        $attempts = array_filter(
            $attempts,
            function ($attempt) use ($USER, $attemptid) {
                if ($attemptid == 0) {
                    return $attempt->userid == $USER->id;
                }

                return $attempt->id == $attemptid;
            }
        );

        if (count($attempts) !== 1) {
            debugging("could not find a single open attempt", DEBUG_DEVELOPER);
            return false;
        }
        debugging("open attempt found", DEBUG_DEVELOPER);

        // get the first (and only) value in the array
        $this->openAttempt = reset($attempts);

        if (isset($this->events) && !isset($this->event)) {
            $event = array_filter(
                $this->events,
                function ($event) {
                    return $event->id == $this->openAttempt->eventid;
                }
            );

            $this->event = reset($event);
        }

        if (isset($this->event)) {
            // update values if null in attempt but exist in event
            if ((!$this->openAttempt->eventid) && ($this->event->id)) {
                $this->openAttempt->eventid = $this->event->id;
            }
            if ((!$this->openAttempt->scenarioid) && ($this->event->scenarioId)) {
                $this->openAttempt->scenarioid = $this->event->scenarioId;
            }

            //TODO remove check for Z once API is updated
            if (strpos($this->event->expirationDate, "Z")) {
                $this->openAttempt->endtime = strtotime($this->event->expirationDate);
            } else if (is_null($this->event->expirationDate)) {
                debugging("event " . $this->event->id . " does not have expirationDate set", DEBUG_DEVELOPER);
                $this->openAttempt->endtime = time() + 28800;
            } else {
                $this->openAttempt->endtime = strtotime($this->event->expirationDate . 'Z');
            }
            $this->openAttempt->save();
        }
        return true;
    }

    public function getall_attempts($state = 'all', $review = false, $userid = 0) {
        global $DB, $USER;

        $user = $USER->id;

        if ($userid) {
            $user = $userid;
        }

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

        if ((!$review) || (!$this->is_instructor())) {
            debugging("getall_attempts for user", DEBUG_DEVELOPER);
            $where[] = '({crucible_attempts}.userid = ? OR {crucible_attempt_users}.userid = ?)';
            $sqlparams[] = $user;
            $sqlparams[] = $user;
        }

        $wherestring = implode(' AND ', $where);

        $sql = "SELECT {crucible_attempts}.* FROM {crucible_attempts} LEFT JOIN {crucible_attempt_users}
        ON {crucible_attempts}.id = {crucible_attempt_users}.attemptid WHERE $wherestring ORDER BY timemodified DESC";
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

        $attempt = $this->get_open_attempt(0);
        if ($attempt === true) {
            debugging("init_attempt found " . $this->openAttempt->id, DEBUG_DEVELOPER);
            return true;
        }
        debugging("init_attempt could not find attempt", DEBUG_DEVELOPER);

        // create a new attempt
        $attempt = new \mod_crucible\crucible_attempt();
        $attempt->userid = $USER->id;
        $attempt->state = \mod_crucible\crucible_attempt::NOTSTARTED;
        $attempt->timemodified = time();
        $attempt->timestart = time();
        $attempt->timefinish = null;
        $attempt->crucibleid = $this->crucible->id;
        $attempt->score = 0;
        if ($this->event->id) {
            $attempt->eventid = $this->event->id;
        } else {
            $attempt->event->id = 0;
        }
        if ($this->event->scenarioId) {
            $attempt->scenarioid = 0;
        }
        //TODO remove check for Z once API is updated
        if (is_null($this->event->expirationDate)) {
            debugging("event " . $this->event->id . " does not have expirationDate set");
            $attempt->endtime = time() + 28800;
        } else if (strpos($this->event->expirationDate, "Z")) {
            $attempt->endtime = strtotime($this->event->expirationDate);
        } else {
            $attempt->endtime = strtotime($this->event->expirationDate . 'Z');
        }

        $attempt->setState('inprogress');

        // TODO get list of tasks from steamfitter
        if ($this->event->scenarioId) {
            debugging("event has a scenarioid", DEBUG_DEVELOPER);
        }
        $attempt->tasks = "";

        $tasks = $DB->get_records('crucible_tasks', array("crucibleid" => $this->crucible->id));

        if ($tasks) {
            foreach ($tasks as $task) {
                $data = new \stdClass();
                $data->taskid = $task->id;
                $data->dispatchtaskid = $task->dispatchtaskid;
                $data->attemptid = $attempt->id;
                $data->vmname = "SUMMARY";
                $data->timemodified = time();

                $rec = $DB->insert_record("crucible_task_results", $data);

	    }
        }

        if ($attempt->save()) {
            $this->openAttempt = $attempt;
        } else {
            return false;
        }

        //TODO call start attempt event class from here
        return true;
    }

    // filter for tasks the user can see and sort by name
    function filter_scenario_tasks($tasks, $visible = 0, $gradable = 0) {

        global $DB;
        if (is_null($tasks)) {
            return;
        }
        $filtered = array();
        foreach ($tasks as $task) {
            if ($task->userExecutable) {
                $filtered[] = $task;
                $task->points = $task->totalScore;
            }
        }

        usort($filtered, "tasksort");
        return $filtered;

    }

    public function get_all_attempts_for_form() {
        global $DB, $USER;

        $attempts = $this->getall_attempts('open');

        $attempts = array_filter(
            $attempts,
            function($attempt) use($USER) {
                return $attempt->userid != $USER->id;
            }
        );

        foreach ($attempts as $attempt) {
            $sql = "select username from {user} where id = ?";
            $sqlparams = [$attempt->userid];
            $username = $DB->get_records_sql($sql, $sqlparams);

            $attempt->username = reset($username)->username;
        }

        return $attempts;
    }

    public function get_all_users_for_attempt($attempt) {
        global $DB;

        $sqlparams = array();
        $where = array();

        $where[] = 'attemptid = ?';
        $sqlparams[] = $attempt->id;

        $wherestring = implode(' AND ', $where);

        $sql = "SELECT userid FROM {crucible_attempt_users} WHERE $wherestring";
        $dbattemptusers = $DB->get_records_sql($sql, $sqlparams);

        $userids = array_column($dbattemptusers, 'userid');
        array_push($userids, $attempt->userid);

        return $userids;
    }


    // GET /events/{eventId}/invite - generate a sharecode for the event
    function generate_sharecode() {

        if ($this->userauth == null) {
            echo 'error with userauth<br>';
            return;
        }

        // web request
        $url = get_config('crucible', 'alloyapiurl') . "/events/" . $this->event->id . "/invite";
        //echo "GET $url<br>";

        $response = $this->userauth->post($url);

        if ($this->userauth->info['http_code']  !== 201) {
            debugging('response code ' . $this->userauth->info['http_code'] . " $url", DEBUG_DEVELOPER);
            return;
        }

        //echo "response:<br><pre>$response</pre>";
        if (!$response) {
            debugging("no response received by list_events $url", DEBUG_DEVELOPER);
            return;
        }

        $r = json_decode($response);

        if (!$r) {
            debugging("could not decode json $url", DEBUG_DEVELOPER);
            return;
        }

        return $r;
    }

    // GET /events/enlist({code} - join an existing event
    function enlist($code) {

        if ($this->userauth == null) {
            echo 'error with userauth<br>';
            return;
        }

        // web request
        $url = get_config('crucible', 'alloyapiurl') . "/events/enlist/" . $code;
        //echo "GET $url<br>";

        $response = $this->userauth->post($url);

        if ($this->userauth->info['http_code']  !== 201) {
            debugging('response code ' . $this->userauth->info['http_code'] . " $url", DEBUG_DEVELOPER);
            return;
        }

        //echo "response:<br><pre>$response</pre>";
        if (!$response) {
            debugging("no response received by list_events $url", DEBUG_DEVELOPER);
            return;
        }

        $r = json_decode($response);

        if (!$r) {
            debugging("could not decode json $url", DEBUG_DEVELOPER);
            return;
        }

        return $r;
    }

    function isEnded() {
        if ($this->event && ($this->event->status == "Ended" || $this->event->status == "Ending")) {
            return true;
        }

        return false;
    }
}
