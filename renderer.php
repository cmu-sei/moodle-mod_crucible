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
 * Renderer class for the Crucible activity module.
 *
 * @package   mod_crucible
 * @copyright 2020 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
 * Renderer for the Crucible activity module.
 *
 * Provides methods for rendering UI components such as attempts, scores, tasks, etc.
 *
 * @package   mod_crucible
 * @copyright 2020 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_crucible_renderer extends plugin_renderer_base {

    /**
     * Displays the crucible activity detail block.
     *
     * @param object $crucible Crucible activity instance.
     * @param string $duration Formatted duration string to display.
     */
    public function display_detail($crucible, $duration) {
        $data = new stdClass();
        $data->name = $crucible->name;
        $data->intro = strip_tags($crucible->intro);
        $data->durationtext = get_string('durationtext', 'mod_crucible');
        $data->duration = $duration;
        echo $this->render_from_template('mod_crucible/detail', $data);
    }

    /**
     * Renders the form for starting or resuming an attempt.
     *
     * @param string $url Form action URL.
     * @param object $eventtemplate The event template data.
     * @param int $id Crucible instance ID.
     * @param int $selectedattempt The ID of the selected attempt, if any.
     * @param array $attempts List of attempt objects.
     * @param string $code Optional access code.
     */
    public function display_form($url, $eventtemplate, $id = 0, $selectedattempt = 0, $attempts = [], $code = '') {
        $data = new stdClass();
        $data->url = $url;
        $data->eventtemplate = $eventtemplate;
        $data->fullscreen = get_string('fullscreen', 'mod_crucible');

        $data->code = $code;
        $data->selectedattempt = $selectedattempt;
        $data->activeAttempts = !empty($attempts);
        $data->id = $id;

        $filteredarray = array_filter($attempts, function($attempt) use($selectedattempt) {
            return $selectedattempt == $attempt->id;
        });

        $joinedattemptobj = reset($filteredarray);

        if ($joinedattemptobj) {
            $data->joinedattempt = true;
            $data->joinedattemptowner = $joinedattemptobj->username;
        }

        $formattempts = [];

        foreach ($attempts as $attempt) {
            $a = new stdClass();
            $a->id = $attempt->id;
            $a->username = $attempt->username;

            array_push($formattempts, $a);
        }

        $data->attempts = $formattempts;

        echo $this->render_from_template('mod_crucible/form', $data);
    }

    /**
     * Renders the invite page for users to join or continue an attempt.
     *
     * @param string $url The URL for form action.
     * @param object $eventtemplate The event template data.
     * @param int $id Crucible instance ID.
     * @param int $selectedattempt ID of the selected attempt (if any).
     * @param array $attempts List of available attempts.
     * @param string $code Optional access code.
     */
    public function display_invite($url, $eventtemplate, $id = 0, $selectedattempt = 0, $attempts = [], $code = '') {
        $data = new stdClass();
        $data->url = $url;
        $data->eventtemplate = $eventtemplate;

        $data->code = $code;
        $data->selectedattempt = $selectedattempt;
        $data->activeattempts = !empty($attempts);
        $data->id = $id;

        $filteredarray = array_filter($attempts, function($attempt) use($selectedattempt) {
            return $selectedattempt == $attempt->id;
        });

        $joinedattemptobj = reset($filteredarray);

        if ($joinedattemptobj) {
            $data->joinedattempt = true;
            $data->joinedattemptowner = $joinedattemptobj->username;
        }

        $formattempts = [];

        foreach ($attempts as $attempt) {
            $a = new stdClass();
            $a->id = $attempt->id;
            $a->username = $attempt->username;

            array_push($formattempts, $a);
        }

        $data->attempts = $formattempts;

        echo $this->render_from_template('mod_crucible/invite', $data);
    }

    /**
     * Displays a return form with a link back to a specific URL and ID.
     *
     * @param string $url The URL the form should point to.
     * @param int $id The identifier associated with the form action.
     */
    public function display_return_form($url, $id) {
        $data = new stdClass();
        $data->url = $url;
        $data->id = $id;
        $data->returntext = get_string('returntext', 'mod_crucible');;
        echo $this->render_from_template('mod_crucible/returnform', $data);
    }

    /**
     * Displays a page with a link to the player application.
     *
     * @param string $playerappurl The base URL of the player application.
     * @param int $viewid The view ID to append to the URL.
     */
    public function display_link_page($playerappurl, $viewid) {
        $data = new stdClass();
        $data->url = $playerappurl . '/view/' .  $viewid;
        $data->playerlinktext = get_string('playerlinktext', 'mod_crucible');
        // Render the data in a Mustache template.
        echo $this->render_from_template('mod_crucible/link', $data);

    }

    /**
     * Displays an embedded view of the Crucible activity.
     *
     * @param stdClass $crucible The Crucible activity object.
     */
    public function display_embed_page($crucible) {
        $data = new stdClass();
        $data->fullscreen = get_string('fullscreen', 'mod_crucible');
        // Render the data in a Mustache template.
        echo $this->render_from_template('mod_crucible/embed', $data);

    }

    /**
     * Displays the grade for a user in a Crucible activity.
     *
     * @param stdClass $crucible The Crucible activity instance.
     * @param int|null $user Optional. The ID of the user to display the grade for. Defaults to the current user.
     */
    public function display_grade($crucible, $user = null) {
        global $USER;

        if (is_null($user)) {
            $user = $USER->id;
        }

        $usergrades = \mod_crucible\utils\grade::get_user_grade($crucible, $user);
        // Should only be 1 grade, but we'll always get end just in case.
        $usergrade = end($usergrades);
        $data = new stdClass();
        $data->overallgrade = get_string('overallgrade', 'groupquiz');
        $data->grade = number_format($usergrade, 2);
        $data->maxgrade = $crucible->grade;
        echo $this->render_from_template('mod_crucible/grade', $data);
    }

    /**
     * Displays a score placeholder for a Crucible attempt.
     *
     * @param stdClass $attempt The attempt object containing attempt data.
     */
    public function display_score($attempt) {
        // global $USER;
        // global $DB;

        // $rec = $DB->get_record("crucible_attempts", array("id" => $attempt));

        $data = new stdClass();
        $data->score = 0;
        $data->maxgrade = 0;
        // $data->score = $rec->score;
        // $data->maxgrade = $DB->get_field("crucible", "grade", array('id' => $rec->crucibleid));
        echo $this->render_from_template('mod_crucible/score', $data);
    }

    /**
     * Displays the score for a given Crucible scenario.
     *
     * @param stdClass $scenario The scenario object containing score information.
     */
    public function display_scenario_score($scenario) {
        // global $USER;
        // global $DB;

        // $rec = $DB->get_record("crucible_attempts", array("id" => $attempt));

        $data = new stdClass();
        $data->score = $scenario->scoreEarned;
        $data->maxgrade = $scenario->score;
        // $data->score = $rec->score;
        // $data->maxgrade = $DB->get_field("crucible", "grade", array('id' => $rec->crucibleid));
        echo $this->render_from_template('mod_crucible/score', $data);
    }

    /**
     * Displays a table of Crucible attempts.
     *
     * @param array $attempts List of attempt objects.
     * @param bool $showgrade Whether to display grades in the table.
     * @param bool $showuser Whether to include user information in the table.
     * @param bool $showdetail Whether to include additional module detail.
     */
    public function display_attempts($attempts, $showgrade, $showuser = false, $showdetail = false) {
        global $DB;
        $data = new stdClass();
        $data->tableheaders = new stdClass();
        $data->tabledata = [];

        if ($showuser) {
            $data->tableheaders->username = get_string('username', 'mod_crucible');
            $data->tableheaders->eventguid = get_string('eventid', 'mod_crucible');
        }
        if ($showdetail) {
            $data->tableheaders->name = get_string('eventtemplate', 'mod_crucible');
        }
        $data->tableheaders->timestart = get_string('timestart', 'mod_crucible');
        $data->tableheaders->timefinish = get_string('timefinish', 'mod_crucible');

        if ($showgrade) {
            $data->tableheaders->score = get_string('score', 'mod_crucible');
        }

        if ($attempts) {
            foreach ($attempts as $attempt) {
                $rowdata = new stdClass();
                if ($showuser) {
                    $user = $DB->get_record("user", ['id' => $attempt->userid]);
                    $rowdata->username = fullname($user);
                    if ($attempt->eventid) {
                        $rowdata->eventguid = $attempt->eventid;
                    } else {
                        $rowdata->eventguid = "-";
                    }
                }
                if ($showdetail) {
                    $crucible = $DB->get_record("crucible", ['id' => $attempt->crucibleid]);
                    $rowdata->name = $crucible->name;
                    $rowdata->moduleurl = new moodle_url('/mod/crucible/view.php', ["c" => $crucible->id]);
                }
                $rowdata->timestart = userdate($attempt->timestart);
                if ($attempt->state == \mod_crucible\crucible_attempt::FINISHED) {
                    $rowdata->timefinish = userdate($attempt->timefinish);
                } else {
                    $rowdata->timefinish = null;
                }
                if ($showgrade) {
                    if ($attempt->score !== null) {
                        $rowdata->score = $attempt->score;
                        $rowdata->attempturl = new moodle_url('/mod/crucible/viewattempt.php', ["a" => $attempt->id]);
                    } else {
                        $rowdata->score = "-";
                    }
                }
                $data->tabledata[] = $rowdata;
            }
        }
        echo $this->render_from_template('mod_crucible/history', $data);
    }

    /**
     * Displays a table of tasks with their details.
     *
     * @param array $tasks List of task objects to be displayed.
     */
    public function display_tasks($tasks) {
        if (is_null($tasks)) {
            return;
        }
        $data = new stdClass();
        $data->tableheaders = [
            // get_string('taskid', 'mod_crucible'),
            get_string('taskname', 'mod_crucible'),
            get_string('taskdesc', 'mod_crucible'),
            get_string('points', 'mod_crucible'),
        ];

        foreach ($tasks as $task) {
            $rowdata = [];
            // $rowdata[] = $task->id;
            $rowdata[] = $task->name;
            if ($task->description) {
                $rowdata[] = $task->description;
            } else {
                $rowdata[] = "No description available";
            }
            if (empty($task->points) && isset($task->score)) {
                $rowdata[] = $task->score;
            } else {
                $rowdata[] = $task->points;
            }            
            $data->tabledata[] = $rowdata;
        }

        echo $this->render_from_template('mod_crucible/tasks', $data);

    }

    /**
     * Displays a form with task details for admin or grading purposes.
     *
     * @param array $tasks List of task objects containing grading and visibility attributes.
     */
    public function display_tasks_form($tasks) {
        global $DB;
        if (is_null($tasks)) {
            return;
        }
        $data = new stdClass();
        $data->tableheaders = [
            get_string('taskid', 'mod_crucible'),
            get_string('taskname', 'mod_crucible'),
            get_string('taskdesc', 'mod_crucible'),
            'vm mask',
            'inputString',
            'expectedOutput',
            'gradable',
            'visible',
            'muliple',
            'points',
        ];

        foreach ($tasks as $task) {
            $rowdata = [];
            $rowdata[] = $task->id;
            $rowdata[] = $task->name;
            $rowdata[] = $task->description;
            $rowdata[] = $task->vmMask;
            $rowdata[] = $task->inputString;
            $rowdata[] = $task->expectedOutput;
            // Get task from db table.
            $rec = $DB->get_record_sql('SELECT * from {crucible_tasks} WHERE '
                    . $DB->sql_compare_text('dispatchtaskid') . ' = '
                    . $DB->sql_compare_text(':dispatchtaskid'), ['dispatchtaskid' => $task->id]);
            $rowdata[] = $rec->gradable;
            $rowdata[] = $rec->visible;
            $rowdata[] = $rec->multiple;
            $rowdata[] = $rec->points;
            $data->tabledata[] = $rowdata;

        }

        echo $this->render_from_template('mod_crucible/tasks_form', $data);

    }

    /**
     * Displays task results in a table, optionally including review-specific columns.
     *
     * @param array $tasks  List of task result objects.
     * @param bool $review  If true, includes review-specific fields such as comments and regrade actions.
     */
    public function display_results($tasks, $review = false) {
        if (is_null($tasks)) {
            return;
        }
        $data = new stdClass();
        if ($review) {
            $data->tableheaders = [
                // get_string('taskid', 'mod_crucible'),
                get_string('taskname', 'mod_crucible'),
                get_string('taskdesc', 'mod_crucible'),
                get_string('taskaction', 'mod_crucible'),
                get_string('taskcomment', 'mod_crucible'),
                get_string('taskresult', 'mod_crucible'),
                get_string('points', 'mod_crucible'),
                get_string('score', 'mod_crucible'),
            ];
        } else {
            $data->tableheaders = [
                // get_string('taskid', 'mod_crucible'),
                get_string('taskname', 'mod_crucible'),
                get_string('taskdesc', 'mod_crucible'),
                get_string('taskaction', 'mod_crucible'),
                get_string('taskresult', 'mod_crucible'),
                get_string('points', 'mod_crucible'),
                get_string('score', 'mod_crucible'),
            ];
        }

        if ($tasks) {

            foreach ($tasks as $task) {
                $rowdata = new stdClass();
                $rowdata->id = $task->id;
                $rowdata->name = $task->name;
                $rowdata->desc = $task->description;
                if (isset($task->totalStatus)) {
                    $rowdata->result = $task->totalStatus;
                }

                if (!$review) {
                    if ($task->userExecutable) {
                        $rowdata->action = get_string('taskexecute', 'mod_crucible');
                    } else {
                        $rowdata->noaction = get_string('tasknoexecute', 'mod_crucible');
                    }
                } else {
                    $rowdata->noaction = get_string('tasknoexecute', 'mod_crucible');
                }
                if ($review) {
                    if (isset($task->comment)) {
                        $rowdata->comment = $task->comment;
                    } else {
                        $rowdata->comment = "-";
                    }
                }
                if (isset($task->totalScoreEarned)) {
                    $rowdata->score = $task->totalScoreEarned;
                }
                $rowdata->points = $task->points;
                $data->tabledata[] = $rowdata;
            }

            echo $this->render_from_template('mod_crucible/results', $data);
        }
    }

    /**
     * Displays detailed task results for a scenario attempt including regrade options and VM names.
     *
     * @param int $a The attempt ID used for linking to editing URLs.
     * @param array $tasks List of task objects with detailed grading information.
     */
    public function display_results_detail($a, $tasks) {
        if (is_null($tasks)) {
            return;
        }
        $data = new stdClass();
        $data->tableheaders = [
            // get_string('taskid', 'mod_crucible'),
            get_string('taskname', 'mod_crucible'),
            get_string('taskdesc', 'mod_crucible'),
            get_string('taskregrade', 'mod_crucible'),
            get_string('vmname', 'mod_crucible'),
            get_string('taskcomment', 'mod_crucible'),
            get_string('taskresult', 'mod_crucible'),
            get_string('points', 'mod_crucible'),
            get_string('score', 'mod_crucible'),
        ];

        if ($tasks) {

            foreach ($tasks as $task) {
                $rowdata = new stdClass();
                $rowdata->id = $task->id;
                $rowdata->name = $task->name;
                $rowdata->desc = $task->description;
                if (isset($task->result)) {
                    $rowdata->result = $task->result;
                }
                if ($task->vmname == null) {
                    $rowdata->action = get_string('taskregrade', 'mod_crucible');
                    $rowdata->url = new moodle_url('/mod/crucible/viewattempt.php',
                                    ["a" => $a, "id" => $task->id, "action" => "edit"]);
                    $rowdata->vmname = "-";
                } else if ($task->vmname === "SUMMARY") {
                    $rowdata->action = get_string('taskregrade', 'mod_crucible');
                    $rowdata->url = new moodle_url('/mod/crucible/viewattempt.php',
                                    ["a" => $a, "id" => $task->id, "action" => "edit"]);
                    $rowdata->vmname = $task->vmname;
                } else {
                    $rowdata->vmname = $task->vmname;
                }
                if (isset($task->comment)) {
                    $rowdata->comment = $task->comment;
                } else {
                     $rowdata->comment = "-";
                }
                if (isset($task->score)) {
                    $rowdata->score = $task->score;
                }
                if (empty($task->points) && isset($task->score)) {
                    $rowdata->points = $task->score;
                } else {
                    $rowdata->points = $task->points;
                }                
                $data->tabledata[] = $rowdata;
            }

            echo $this->render_from_template('mod_crucible/resultsdetail', $data);
        }
    }

    /**
     * Displays a countdown or timer clock for the Crucible activity.
     *
     * @param int $starttime The Unix timestamp when the event starts.
     * @param int $endtime The Unix timestamp when the event ends.
     * @param bool $extend Whether the event allows for extension.
     */
    public function display_clock($starttime, $endtime, $extend = false) {

        $data = new stdClass();
        $data->starttime = $starttime;
        $data->endtime = $endtime;
        if ($extend) {
            $data->extend = get_string('extendevent', 'mod_crucible');
        }

        echo $this->render_from_template('mod_crucible/clock', $data);
    }
}
