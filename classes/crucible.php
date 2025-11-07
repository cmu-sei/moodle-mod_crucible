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

/**
 * crucible Attempt wrapper class to encapsulate functions needed to individual
 * attempt records
 *
 * @package     mod_crucible
 * @copyright   2020 Carnegie Mellon Univeristy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

/**
 * Class representing Crucible attempt handling logic for mod_crucible.
 *
 * This class encapsulates access to event data, attempt tracking, instructor
 * privileges, and integration with Alloy API for managing lab events.
 *
 * @package     mod_crucible
 * @copyright   2020 Carnegie Mellon University
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class crucible {

    /** @var object Event object for the current session */
    public $event;

    /** @var object[] List of all user events */
    public $events;

    /** @var object Crucible activity instance */
    public $crucible;

    /** @var \mod_crucible\crucible_attempt Currently active attempt */
    public $openattempt; // Renamed from $openattempt.

    /** @var object Event template metadata */
    public $eventtemplate;

    /** @var \context_module Moodle context for the module */
    protected $context;

    /** @var bool Whether the user is an instructor */
    protected $isinstructor;

    /** @var object System authentication object */
    public $systemauth;

    /** @var object User authentication object */
    public $userauth;

    /** @var \stdClass Course module object */
    public $cm;

    /** @var \stdClass Course object */
    public $course;

    /** @var array Page variables passed to this instance */
    public $pagevars;

    /** @var \mod_crucible\output\renderer Renderer for this module */
    public $renderer;

    /**
     * Construct class
     *
     * @param object $cm The course module instance
     * @param object $course The course object the activity is contained in
     * @param object $crucible The specific crucible record for this activity
     * @param \moodle_url $pageurl The page url
     * @param array  $pagevars The variables and options for the page
     * @param string $renderersubtype Renderer sub-type to load if requested
     *
     */
    public function __construct($cm, $course, $crucible, $pageurl, $pagevars = [], $renderersubtype = null) {
        global $CFG, $PAGE;

        $this->cm = $cm;
        $this->course = $course;
        $this->crucible = $crucible;
        $this->pagevars = $pagevars;

        $this->context = \context_module::instance($cm->id);
        $PAGE->set_context($this->context);

        $this->is_instructor();

        $this->systemauth = setup_system();
        $this->userauth = setup(); // Fails when called by runtask.

        $this->renderer = $PAGE->get_renderer('mod_crucible', $renderersubtype);
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
            // Pass in userid if there is one.
            return has_capability($capability, $this->context, $userid);
        } else {
            // Just do standard check with current user.
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

    // GET /eventtemplates/{eventtemplateId}/events/mine -- Gets the user's Implementations for the indicated Definition.
    /**
     * Retrieves a list of events for the current user based on the event template.
     *
     * @return array|null An array of user events or null on failure.
     */
    public function list_events() {

        if ($this->userauth === null) {
            echo 'error with userauth<br>';
            return;
        }

        // Web request.
        // Build URL to retrieve user's events for the given event template.
        $url = get_config('crucible', 'alloyapiurl') .
                '/eventtemplates/' . $this->crucible->eventtemplateid .
                '/events/mine?includeInvites=true';

        $response = $this->userauth->get($url);

        if ($this->userauth->info['http_code'] !== 200) {
            debugging('response code ' . $this->userauth->info['http_code'] . " $url", DEBUG_DEVELOPER);
            return;
        }

        if (!$response) {
            debugging("no response received by list_events $url", DEBUG_DEVELOPER);
            return;
        }

        $r = json_decode($response, true);

        if (!$r) {
            // This can mean the user made 0 attempts at this event.
            debugging("could not decode json $url", DEBUG_DEVELOPER);
            return;
        }

        usort($r, 'launchDate');
        return $r;
    }

    /**
     * Retrieves a single Crucible attempt by its ID.
     *
     * @param int $attemptid The ID of the attempt to retrieve.
     * @return crucible_attempt The corresponding attempt object.
     */
    public function get_attempt($attemptid) {
        global $DB;

        $dbattempt = $DB->get_record('crucible_attempts', ['id' => $attemptid]);

        return new crucible_attempt($dbattempt);
    }

    /**
     * Retrieves and sets the open attempt for a given attempt ID, or for the current user if ID is 0.
     *
     * This function looks through all open attempts and sets the one that matches the attempt ID
     * or belongs to the current user. It also links the associated event and sets expiration details.
     *
     * @param int $attemptid The ID of the attempt to retrieve (0 to search by user).
     * @return bool True if a matching open attempt is found and set, false otherwise.
     */
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
            debugging("could not find exactly one open attempt", DEBUG_DEVELOPER);
            return false;
        }
        debugging("open attempt found", DEBUG_DEVELOPER);

        // Get the first (and only) value in the array.
        $this->openattempt = reset($attempts);

        if (isset($this->events) && !isset($this->event)) {
            $event = array_filter(
                $this->events,
                function ($event) {
                    return $event->id == $this->openattempt->eventid;
                }
            );

            $this->event = reset($event);
        }

        if (isset($this->event)) {
            // Update values if null in attempt but exist in event.
            if ((!$this->openattempt->eventid) && ($this->event->id)) {
                $this->openattempt->eventid = $this->event->id;
            }
            if ((!$this->openattempt->scenarioid) && ($this->event->scenarioId)) {
                $this->openattempt->scenarioid = $this->event->scenarioId;
            }

            // TODO remove check for Z once API is updated.
            if (strpos($this->event->expirationDate, "Z")) {
                $this->openattempt->endtime = strtotime($this->event->expirationDate);
            } else if (is_null($this->event->expirationDate)) {
                debugging("event " . $this->event->id . " does not have expirationDate set", DEBUG_DEVELOPER);
                $this->openattempt->endtime = time() + 28800;
            } else {
                $this->openattempt->endtime = strtotime($this->event->expirationDate . 'Z');
            }
            $this->openattempt->save();
        }
        return true;
    }

    /**
     * Retrieves all Crucible attempts for a given user and state.
     *
     * This method can filter attempts by state (open, closed, or all),
     * and optionally include attempts for review if the user is an instructor.
     *
     * @param string $state Filter by attempt state: 'all', 'open', or 'closed'.
     * @param bool $review Whether to include all user attempts (instructor view).
     * @param int $userid Optional specific user ID to fetch attempts for. Defaults to current user.
     * @return crucible_attempt[] Array of Crucible attempt objects.
     */
    public function getall_attempts($state = 'all', $review = false, $userid = 0) {
        global $DB, $USER;

        $user = $USER->id;

        if ($userid) {
            $user = $userid;
        }

        $sqlparams = [];
        $where = [];

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
                // Add no condition for state when 'all' or something other than open/closed.
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

        $attempts = [];
        // Create array of class attempts from the db entry.
        foreach ($dbattempts as $dbattempt) {
            $attempts[] = new crucible_attempt($dbattempt);
        }

        return $attempts;
    }

    /**
     * Retrieves all Crucible attempts for a specific user.
     *
     * This method fetches attempts made by a specific user, optionally filtered by state
     * ('open', 'closed', or 'all') and review mode for instructors.
     *
     * @param int $userid The user ID whose attempts are to be fetched.
     * @param string $state The state of the attempts to retrieve: 'open', 'closed', or 'all'.
     * @param bool $review Whether to include attempts as an instructor for review purposes.
     * @return crucible_attempt[] Array of Crucible attempt objects for the given user.
     */
    public function get_attempts_by_user($userid, $state = 'all', $review = false) {
        global $DB;

        $sqlparams = [];
        $where = [];

        $where[] = '{crucible_attempts}.crucibleid = ?';
        $sqlparams[] = $this->crucible->id;

        switch ($state) {
            case 'open':
                $where[] = '{crucible_attempts}.state = ?';
                $sqlparams[] = crucible_attempt::INPROGRESS;
                break;
            case 'closed':
                $where[] = '{crucible_attempts}.state = ?';
                $sqlparams[] = crucible_attempt::FINISHED;
                break;
            default:
                // No additional state filtering.
        }

        // Always filter by user.
        $where[] = '({crucible_attempts}.userid = ? OR {crucible_attempt_users}.userid = ?)';
        $sqlparams[] = $userid;
        $sqlparams[] = $userid;

        $wherestring = implode(' AND ', $where);

        $sql = "SELECT {crucible_attempts}.*
                FROM {crucible_attempts}
                LEFT JOIN {crucible_attempt_users}
                ON {crucible_attempts}.id = {crucible_attempt_users}.attemptid
                WHERE $wherestring
                ORDER BY timemodified DESC";

        $dbattempts = $DB->get_records_sql($sql, $sqlparams);

        $attempts = [];
        foreach ($dbattempts as $dbattempt) {
            $attempts[] = new crucible_attempt($dbattempt);
        }

        return $attempts;
    }

    /**
     * Initializes a new Crucible attempt for the current user if none exists.
     *
     * If an open attempt does not already exist, this method creates one,
     * associates it with the current Crucible event, and saves it to the database.
     *
     * @return bool True if the attempt was successfully initialized or already exists, false on failure.
     */
    public function init_attempt() {
        global $DB, $USER;

        $attempt = $this->get_open_attempt(0);
        if ($attempt === true) {
            debugging("init_attempt found " . $this->openattempt->id, DEBUG_DEVELOPER);
            return true;
        }
        debugging("init_attempt could not find attempt", DEBUG_DEVELOPER);

        // Create a new attempt.
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
        // TODO remove check for Z once API is updated.
        if (is_null($this->event->expirationDate)) {
            debugging("event " . $this->event->id . " does not have expirationDate set");
            $attempt->endtime = time() + 28800;
        } else if (strpos($this->event->expirationDate, "Z")) {
            $attempt->endtime = strtotime($this->event->expirationDate);
        } else {
            $attempt->endtime = strtotime($this->event->expirationDate . 'Z');
        }

        $attempt->setstate('inprogress');

        // TODO get list of tasks from steamfitter.
        if ($this->event->scenarioId) {
            debugging("event has a scenarioid", DEBUG_DEVELOPER);
        }
        $attempt->tasks = "";

        $tasks = $DB->get_records('crucible_tasks', ['crucibleid' => $this->crucible->id]);

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
            $this->openattempt = $attempt;
        } else {
            return false;
        }

        // TODO call start attempt event class from here.
        return true;
    }

    /**
     * Filters scenario tasks based on visibility and executability.
     *
     * @param array $tasks List of scenario task objects to filter.
     * @param bool $isvisible Whether to include only tasks marked as visible.
     * @param bool $isexecutable Whether to include only tasks the user can execute.
     * @return array Filtered and sorted list of task objects.
     */
    public function filter_scenario_tasks($tasks, $isvisible = false, $isexecutable = false) {
        global $DB;
    
        if (is_null($tasks)) {
            return [];
        }
    
        $filtered = [];
    
        foreach ($tasks as $task) {
            $include = true;
    
            // Look up task visibility, gradable, and points by name.
            $sql = 'SELECT visible, gradable, points FROM {crucible_tasks} WHERE ' .
                   $DB->sql_compare_text('name') . ' = ' . $DB->sql_compare_text(':name');
            $rec = $DB->get_record_sql($sql, ['name' => $task->name]);
    
            // Filter by visibility flag if required.
            if ($isvisible && (!$rec || empty($rec->visible))) {
                $include = false;
            }
    
            // Filter by executable flag from task object.
            if ($isexecutable && empty($task->userExecutable)) {
                $include = false;
            }
    
            // Include task if it passes all checks.
            if ($include) {
                // Set points if task is gradable in DB.
                if ($rec && !empty($rec->gradable)) {
                    $task->points = $rec->points ?? 1; // default to 1 if missing
                } else {
                    $task->points = 0; // not gradable
                }
    
                $filtered[] = $task;
            }
        }
    
        usort($filtered, "tasksort");
        return $filtered;
    }    

    /**
     * Retrieves all open attempts that do not belong to the current user.
     *
     * This is used for displaying attempts in a form where the current user
     * may need to select or view attempts from other users.
     *
     * @return array List of attempt objects with usernames attached.
     */
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

    /**
     * Retrieves all user IDs associated with a given attempt.
     *
     * This includes the owner of the attempt and any users linked via the
     * crucible_attempt_users table.
     *
     * @param object $attempt The attempt object containing the attempt ID.
     * @return array List of user IDs involved in the attempt.
     */
    public function get_all_users_for_attempt($attempt) {
        global $DB;

        $sqlparams = [];
        $where = [];

        $where[] = 'attemptid = ?';
        $sqlparams[] = $attempt->id;

        $wherestring = implode(' AND ', $where);

        $sql = "SELECT userid FROM {crucible_attempt_users} WHERE $wherestring";
        $dbattemptusers = $DB->get_records_sql($sql, $sqlparams);

        $userids = array_column($dbattemptusers, 'userid');
        array_push($userids, $attempt->userid);

        return $userids;
    }


    // GET /events/{eventId}/invite - generate a sharecode for the event.
    /**
     * Generates a share code for the current event by calling the Alloy API.
     *
     * @return mixed The decoded API response object on success, or null on failure.
     */
    public function generate_sharecode() {

        if ($this->userauth === null) {
            echo 'error with userauth<br>';
            return;
        }

        // Web request.
        $url = get_config('crucible', 'alloyapiurl') . "/events/" . $this->event->id . "/invite";

        $response = $this->userauth->post($url);

        if ($this->userauth->info['http_code'] !== 201) {
            debugging('response code ' . $this->userauth->info['http_code'] . " $url", DEBUG_DEVELOPER);
            return;
        }

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

    // GET /events/enlist({code} - join an existing event.
    /**
     * Joins an existing event using the provided share code by calling the Alloy API.
     *
     * @param string $code The invitation code to enlist in the event.
     * @return mixed The decoded API response object on success, or null on failure.
     */
    public function enlist($code) {

        if ($this->userauth === null) {
            echo 'error with userauth<br>';
            return false;
        }

        // Web request.
        $url = get_config('crucible', 'alloyapiurl') . "/events/enlist/" . $code;

        $response = $this->userauth->post($url);

        if ($this->userauth->info['http_code'] !== 201) {
            debugging('response code ' . $this->userauth->info['http_code'] . " $url", DEBUG_DEVELOPER);
            return false;
        }

        if (!$response) {
            debugging("no response received by enlist $url", DEBUG_DEVELOPER);
            return false;
        }

        $r = json_decode($response);

        if (!$r) {
            debugging("could not decode json $url", DEBUG_DEVELOPER);
            return false;
        }

        return $r;
    }

    /**
     * Determines if the current event has ended or is ending.
     *
     * @return bool True if the event is in 'Ended' or 'Ending' state, false otherwise.
     */
    public function is_ended() {
        if ($this->event && ($this->event->status == "Ended" || $this->event->status == "Ending")) {
            return true;
        }

        return false;
    }
}
