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

namespace mod_crucible\utils;

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

class grade {

    /** @var \mod_crucible\crucible */
    public $crucible;

    /**
     * Construct for the grade utility class
     *
     * @param \mod_crucible\crucible $crucible
     */
    public function __construct($crucible) {
        $this->crucible = $crucible;
    }

    /**
     * Get the attempt's grade
     *
     * For now this will always be the last attempt for the user
     *
     * @param \mod_crucible\crucible_attempt $attempt
     * @param int                                $userid The userid to get the grade for
     * @return array($forgroupid, $number)
     */
    protected function get_attempt_grade($attempt) {
        return array($attempt->userid, $this->calculate_attempt_grade($attempt));
    }

    /**
     * Gets the user grade, userid can be 0, which will return all grades for the crucible
     *
     * @param $crucible
     * @param $userid
     * @return array
     */
    public static function get_user_grade($crucible, $userid = 0) {

        global $DB;
        $recs = $DB->get_records_select('crucible_grades', 'userid = ? AND crucibleid = ?',
                array($userid, $crucible->id), 'grade');
        $grades = array();
        foreach ($recs as $rec) {
            array_push($grades, $rec->grade);
        }
        // user should only have one grade entry in the crucible grades table for each activity
        debugging("user $userid has " . count($grades) . " grades for $crucible->id", DEBUG_DEVELOPER);
        return $grades;
    }

    public function process_attempt($attempt) {
        global $DB;

        // get this attempt grade
        $this->calculate_attempt_grade($attempt);

        // get all attempt grades
        $grades = array();
        $attemptsgrades = array();

        // TODO should we be processing just one user here?
        $attempts = $this->crucible->getall_attempts('');

        foreach ($attempts as $attempt) {
            array_push($attemptsgrades, $attempt->score);
        }

        $grade = $this->apply_grading_method($attemptsgrades);
        $grades[$attempt->userid] = $grade;
        debugging("new grade for $attempt->userid in crucible " . $this->crucible->crucible->id . " is $grade", DEBUG_DEVELOPER);

        // run the whole thing on a transaction (persisting to our table and gradebook updates).
        $transaction = $DB->start_delegated_transaction();

        // now that we have the final grades persist the grades to crucible grades table.
        //TODO we could possibly remove this table and just look at the grade_grades table
        $this->persist_grades($grades, $transaction);

        // update grades to gradebookapi.
        $updated = crucible_update_grades($this->crucible->crucible, $attempt->userid, $grade);

        if ($updated === GRADE_UPDATE_FAILED) {
            $transaction->rollback(new \Exception('Unable to save grades to gradebook'));
        }

        // Allow commit if we get here
        $transaction->allow_commit();

        // if everything passes to here return true
        return true;

    }

    /**
     * Calculate the grade for attempt passed in
     *
     * This function does the scaling down to what was desired in the crucible settings
     *
     * Is public function so that tableviews can get an attempt calculated grade
     *
     * @param \mod_crucible\crucible_attempt $attempt
     * @return number The grade to save
     */
    public function calculate_attempt_grade($attempt) {
    
        $totalpoints = 0;
        $totalslotpoints = 0;

        if (is_null($attempt)) {
            return $totalslotpoints;
        }

        if ($this->crucible->openAttempt->sessionid) {
            //$tasks = filter_tasks(get_sessiontasks($this->crucible->systemauth, $this->crucible->openAttempt->sessionid));
            //$taskresults = get_taskresults($this->crucible->systemauth, $this->crucible->openAttempt->sessionid);
            $tasks = filter_tasks(get_sessiontasks($this->crucible->userauth, $this->crucible->openAttempt->sessionid));
            $taskresults = get_taskresults($this->crucible->userauth, $this->crucible->openAttempt->sessionid);
        } else {
            debugging("attempt $attempt->id has no steamfitter tasks to grade", DEBUG_DEVELOPER);
            return $totalslotpoints;
        }

        if (empty($taskresults)) {
            debugging("no taskresults found in session " . $this->crucible->openAttempt->sessionid, DEBUG_DEVELOPER);
            return $totalslotpoints;
        }

        //TODO make sure that results are time sorted first

        $values = array();
        foreach ($taskresults as $result) {
            // find task in tasks and update the result
            foreach ($tasks as $task) {
                if ($task->id == $result->dispatchTaskId) {
                    $values[$task->id] = $result->status;
                    debugging("task " . $task->id . " status " . $result->status, DEBUG_DEVELOPER);
                }
            }
        }
        foreach ($values as $value) {
            if ($value === "succeeded") {
                $totalslotpoints++;
            }
        }

        // TODO one day check attribute for whether the task is gradable or not
        $totalpoints = count($tasks);
        $scaledpoints = ($totalslotpoints / $totalpoints) *  $this->crucible->crucible->grade;
        debugging("new score for $attempt->id is $scaledpoints", DEBUG_DEVELOPER);

        $attempt->score = $scaledpoints;
        $attempt->save();

        return $scaledpoints;
    }

    /**
     * Helper function that returns the grade to pass.
     *
     * @return string
     */
    public function get_grade_item_passing_grade() {
        global $DB;

        $gradetopass = $DB->get_field('grade_items', 'gradepass', array('iteminstance' => $this->crucible->crucible->id, 'itemmodule' => 'crucible'));

        return $gradetopass;
    }

    /**
     * Applies the grading method chosen
     *
     * @param array $grades The grades for each session for a particular user
     * @return number
     * @throws \Exception When there is no valid scaletype throws new exception
     */
    protected function apply_grading_method($grades) {
        debugging("grade method is " . $this->crucible->crucible->grademethod . " for " . $this->crucible->crucible->id, DEBUG_DEVELOPER);
        switch ($this->crucible->crucible->grademethod) {
            case \mod_crucible\utils\scaletypes::crucible_FIRSTATTEMPT:
                // take the first record (as there should only be one since it was filtered out earlier)
                reset($grades);
                return current($grades);

                break;
            case \mod_crucible\utils\scaletypes::crucible_LASTATTEMPT:
                // take the last grade (there should only be one, as the last session was filtered out earlier)
                return end($grades);

                break;
            case \mod_crucible\utils\scaletypes::crucible_ATTEMPTAVERAGE:
                // average the grades
                $gradecount = count($grades);
                $gradetotal = 0;
                foreach ($grades as $grade) {
                    $gradetotal = $gradetotal + $grade;
                }
                return $gradetotal / $gradecount;

                break;
            case \mod_crucible\utils\scaletypes::crucible_HIGHESTATTEMPTGRADE:
                // find the highest grade
                $highestgrade = 0;
                foreach ($grades as $grade) {
                    if ($grade > $highestgrade) {
                        $highestgrade = $grade;
                    }
                }
                return $highestgrade;

                break;
            default:
                throw new \Exception('Invalid session grade method');
                break;
        }
    }

    /**
     * Persist the passed in grades (keyed by userid) to the database
     *
     * @param array               $grades
     * @param \moodle_transaction $transaction
     *
     * @return bool
     */

    protected function persist_grades($grades, \moodle_transaction $transaction) {
        global $DB;

        foreach ($grades as $userid => $grade) {

            if ($usergrade = $DB->get_record('crucible_grades', array('userid' => $userid, 'crucibleid' => $this->crucible->crucible->id))) {
                // we're updating

                $usergrade->grade = $grade;
                $usergrade->timemodified = time();

                if (!$DB->update_record('crucible_grades', $usergrade)) {
                    $transaction->rollback(new \Exception('Can\'t update user grades'));
                }
            } else {
                // we're adding

                $usergrade = new \stdClass();
                $usergrade->crucibleid = $this->crucible->crucible->id;
                $usergrade->userid = $userid;
                $usergrade->grade = $grade;
                $usergrade->timemodified = time();

                if (!$DB->insert_record('crucible_grades', $usergrade)) {
                    $transaction->rollback(new \Exception('Can\'t insert user grades'));
                }

            }
            debugging("persisted $grade for $userid in crucible " . $this->crucible->crucible->id, DEBUG_DEVELOPER);
        }

        return true;

    }
}
