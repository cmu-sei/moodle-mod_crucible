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
 * @package   mod_crucible
 * @copyright 2020 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

defined('MOODLE_INTERNAL') || die();

class mod_crucible_renderer extends plugin_renderer_base {

    function display_detail ($crucible) {
        $data = new stdClass();
        $data->name = $crucible->name;
        $data->intro = $crucible->intro;
        echo $this->render_from_template('mod_crucible/detail', $data);

    }

    function display_form($url, $eventtemplate) {
        $data = new stdClass();
        $data->url = $url;
        $data->eventtemplate = $eventtemplate;
        echo $this->render_from_template('mod_crucible/form', $data);

    }

    function display_link_page($player_app_url, $exerciseid) {
        $data = new stdClass();
        $data->url =  $player_app_url . '/exercise-player/' .  $exerciseid;
        $data->playerlinktext = get_string('playerlinktext', 'mod_crucible');
        // Render the data in a Mustache template.
        echo $this->render_from_template('mod_crucible/link', $data);

    }

    function display_embed_page($crucible) {
        $data = new stdClass();
        // Render the data in a Mustache template.
        echo $this->render_from_template('mod_crucible/embed', $data);

    }

    function display_attempts($attempts, $showfailed) {
        $data = new stdClass();
        $data->tableheaders = [
            get_string('eventid', 'mod_crucible'),
            //get_string('state', 'mod_crucible'),
            get_string('timestart', 'mod_crucible'),
            get_string('timefinish', 'mod_crucible'),
            get_string('tasks', 'mod_crucible'),
            get_string('score', 'mod_crucible'),
        ];

        if ($attempts) {
            foreach ($attempts as $attempt) {
                $rowdata = array();
                $rowdata[] = $attempt->eventid;
                //$rowdata[] = $attempt->getState();
                $rowdata[] = userdate($attempt->timestart);
                $rowdata[] = userdate($attempt->timefinish);
                $rowdata[] = $attempt->tasks;
                $rowdata[] = $attempt->score;
                $data->tabledata[] = $rowdata;
            }
        }
        echo $this->render_from_template('mod_crucible/history', $data);
    }

    function display_history($history, $showfailed) {
        $data = new stdClass();
        $data->tableheaders = [
            get_string('id', 'mod_crucible'),
            get_string('status', 'mod_crucible'),
            get_string('launchdate', 'mod_crucible'),
            get_string('enddate', 'mod_crucible'),

        ];

        if ($history) {
            foreach ($history as $odx) {
                if ((!$showfailed) && ($odx['status'] === 'Failed')) {
                    continue;
                }
                $rowdata = array();
                $rowdata[] = $odx['id'];
                $rowdata[] = $odx['status'];
                $rowdata[] = $odx['launchDate'];
                $rowdata[] = $odx['endDate'];
                $data->tabledata[] = $rowdata;
            }
        }
        echo $this->render_from_template('mod_crucible/history', $data);
    }

    function display_tasks($tasks) {
        $data = new stdClass();
        $data->tableheaders = [
            //get_string('taskid', 'mod_crucible'),
            get_string('taskname', 'mod_crucible'),
            get_string('taskdesc', 'mod_crucible'),
        ];

        foreach ($tasks as $task) {
            //var_dump($task);
            $rowdata = array();
            //$rowdata[] = $task->id;
            $rowdata[] = $task->name;
            $rowdata[] = $task->description;
            $data->tabledata[] = $rowdata;
        }

        echo $this->render_from_template('mod_crucible/tasks', $data);

    }

    function display_results($tasks) {
        $data = new stdClass();
        $data->tableheaders = [
            //get_string('taskid', 'mod_crucible'),
            get_string('taskname', 'mod_crucible'),
            get_string('taskdesc', 'mod_crucible'),
            get_string('taskaction', 'mod_crucible'),
            get_string('taskresult', 'mod_crucible'),
        ];

        foreach ($tasks as $task) {
            $rowdata = new stdClass();
            $rowdata->id = $task->id;
            $rowdata->name = $task->name;
            $rowdata->desc = $task->description;
            $rowdata->result = $task->result->status;
            // check whether we can execute the task
            if ($task->triggerCondition == "Manual") {
                $rowdata->action = get_string('taskexecute', 'mod_crucible');
            } else {
                $rowdata->action = "";
            }
            $data->tabledata[] = $rowdata;
        }

        echo $this->render_from_template('mod_crucible/results', $data);

    }

    function display_clock($starttime, $endtime) {

        $data = new stdClass();
        $data->starttime = $starttime;
        $data->endtime = $endtime;

        echo $this->render_from_template('mod_crucible/clock', $data);
    }
}


