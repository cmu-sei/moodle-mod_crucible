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
 * Utility class for handling grading operations in Crucible activities.
 *
 * Provides methods to calculate, process, and persist grades for attempts.
 *
 * @package    mod_crucible
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
    public function get_attempt_grade($attempt) {
        return [$attempt->userid, $this->calculate_attempt_grade($attempt)];
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
                [$userid, $crucible->id], 'grade');
        $grades = [];
        foreach ($recs as $rec) {
            array_push($grades, $rec->grade);
        }
        // User should only have one grade entry in the crucible grades table for each activity.
        debugging("user $userid has " . count($grades) . " grades for $crucible->id", DEBUG_DEVELOPER);
        return $grades;
    }

    /**
     * Processes a Crucible attempt by calculating grades and updating gradebook entries.
     *
     * This method determines grades for all users associated with the given attempt,
     * applies the configured grading method, stores grades in the Crucible table,
     * and synchronizes them with the Moodle gradebook.
     *
     * @param \mod_crucible\crucible_attempt $attempt The attempt to process.
     * @return bool True on successful processing and commit.
     * @throws \Exception If gradebook update or database persistence fails.
     */
    public function process_attempt($attempt) {
        global $DB;

        // Get this attempt grade.
        $this->calculate_attempt_grade($attempt);

        // Get all attempt grades.
        $grades = [];
        $attemptsgrades = [];

        // Get all users in attempt.
        $userids = $this->crucible->get_all_users_for_attempt($attempt);

        foreach ($userids as $userid) {
            $attempts = $this->crucible->getall_attempts('', false, $userid);

            foreach ($attempts as $atmpt) {
                array_push($attemptsgrades, $atmpt->score);
            }

            $finishedattempts = array_filter($attempts, function($atmpt) {
                return $atmpt->timefinish != null;
            });

            usort($finishedattempts, function($a, $b) {
                return $a->timefinish - $b->timefinish;
            });

            $firstFinishedAttempt = reset($finishedattempts);
            $firstScore = is_object($firstFinishedAttempt) ? $firstFinishedAttempt->score : 0;
            $grade = $this->apply_grading_method($attemptsgrades, $attempt->score, $firstScore);

            if (!is_null($grade)) {
                $grades[$userid] = $grade;
                debugging("new grade for $userid in crucible " . $this->crucible->crucible->id . " is $grade", DEBUG_DEVELOPER);
            } else {
                debugging("discarding NULL grade for $userid in crucible " . $this->crucible->crucible->id, DEBUG_DEVELOPER);
            }
        }

        // Run the whole thing on a transaction (persisting to our table and gradebook updates).
        $transaction = $DB->start_delegated_transaction();

        // Now that we have the final grades persist the grades to crucible grades table.
        // TODO we could possibly remove this table and just look at the grade_grades table.
        $this->persist_grades($grades, $transaction);

        // Update grades to gradebookapi.
        foreach ($userids as $userid) {
            $updated = crucible_update_grades($this->crucible->crucible, $userid, false);

            if ($updated === GRADE_UPDATE_FAILED) {
                $transaction->rollback(new \Exception('Unable to save grades to gradebook'));
            }
        }

        // Allow commit if we get here.
        $transaction->allow_commit();

        // If everything passes to here return true.
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
        $score = 0;

        if (is_null($attempt)) {
            debugging("invalid attempt passed to calculate_attempt_grade", DEBUG_DEVELOPER);
            return $score;
        }

        if (is_null($attempt->scenarioid)) {
            debugging("no scenarioid passed to calculate_attempt_grade", DEBUG_DEVELOPER);
            return $score;
        }

        $system = setup_system();
        $scenario = get_scenario($system, $attempt->scenarioid);

        $score = $scenario->scoreEarned;

        debugging("new score for $attempt->id is $score", DEBUG_DEVELOPER);

        $attempt->score = $score;
        $attempt->save();

        return $score;
    }

    /**
     * Helper function that returns the grade to pass.
     *
     * @return string
     */
    public function get_grade_item_passing_grade() {
        global $DB;

        $gradetopass = $DB->get_field('grade_items', 'gradepass',
                        ['iteminstance' => $this->crucible->crucible->id, 'itemmodule' => 'crucible']);

        return $gradetopass;
    }

    /**
     * Applies the grading method chosen
     *
     * @param array $grades The grades for each attempts for a particular user
     * @return number
     * @throws \Exception When there is no valid scaletype throws new exception
     */
    protected function apply_grading_method($grades, $mostrecentgrade, $firstgrade) {
        debugging("grade method is " . $this->crucible->crucible->grademethod .
                    " for " . $this->crucible->crucible->id, DEBUG_DEVELOPER);
        switch ($this->crucible->crucible->grademethod) {
            case \mod_crucible\utils\scaletypes::CRUCIBLE_FIRSTATTEMPT:
                return $firstgrade;

                break;
            case \mod_crucible\utils\scaletypes::CRUCIBLE_LASTATTEMPT:
                return $mostrecentgrade;

                break;
            case \mod_crucible\utils\scaletypes::CRUCIBLE_ATTEMPTAVERAGE:
                // Average the grades.
                $gradecount = count($grades);
                $gradetotal = 0;
                foreach ($grades as $grade) {
                    $gradetotal = $gradetotal + $grade;
                }
                return $gradetotal / $gradecount;

                break;
            case \mod_crucible\utils\scaletypes::CRUCIBLE_HIGHESTATTEMPTGRADE:
                // Find the highest grade.
                $highestgrade = 0;
                foreach ($grades as $grade) {
                    if (is_numeric($grade) && $grade > $highestgrade) {
                        $highestgrade = $grade;
                    }
                }
                return $highestgrade;

                break;
            default:
                throw new \Exception('Invalid grade method');
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

            if ($usergrade = $DB->get_record('crucible_grades',
                ['userid' => $userid, 'crucibleid' => $this->crucible->crucible->id])) {
                // We're updating.

                $usergrade->grade = $grade;
                $usergrade->timemodified = time();

                if (!$DB->update_record('crucible_grades', $usergrade)) {
                    $transaction->rollback(new \Exception('Can\'t update user grades'));
                }
            } else {
                // We're adding.

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
