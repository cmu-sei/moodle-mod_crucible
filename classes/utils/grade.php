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
     * Gets the user grade, userid can be 0, which will return all grades for the groupquiz
     *
     * @param $groupquiz
     * @param $userid
     * @return array
     */
    public static function get_user_grade($groupquiz, $userid = 0) {
        global $DB;
        $recs = $DB->get_records_select('groupquiz_grades', 'userid = ?',
                array($userid), 'grade');
        $grades = array();
        foreach ($recs as $rec) {
            array_push($grades, $rec->grade);
        }
        return $grades;
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

        if ($this->crucible->openAttempt->sessionid) {
            $tasks = filter_tasks(get_sessiontasks($this->crucible->systemauth, $this->crucible->openAttempt->sessionid));
            $taskresults = get_taskresults($this->crucible->systemauth, $this->crucible->openAttempt->sessionid);
        } else {
            return;
        }
        $totalpoints = 0;
        $totalslotpoints = 0;

        //TODO make sure that results are time sorted first

        $values = array();
        foreach ($taskresults as $result) {
            // find task in tasks and update the result
            foreach ($tasks as $task) {
                if ($task->id == $result->dispatchTaskId) {
                    $values[$task->id] = $result->status;
                }
            }
        }
        foreach ($values as $value) {
            $totalpoints++;
            if ($value === "succeeded") {
                $totalslotpoints++;
            }
        }

        $scaledpoints = ($totalslotpoints / $totalpoints) *  $this->crucible->crucible->grade;

        $attempt->score = $scaledpoints;
        $attempt->save();

        return $scaledpoints;
    }
}
