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
 * crucible configuration form
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

namespace mod_crucible;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/crucible/locallib.php');

/**
 * Form class for configuring Crucible tasks in a Moodle module instance.
 *
 * @package    mod_crucible
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class crucible_tasks_form extends \moodleform {

    /**
     * Defines the form fields for configuring Crucible tasks.
     */
    public function definition() {
        global $COURSE, $CFG, $DB, $PAGE;
        $mform = $this->_form;
        $index = 0;
        $mform->addElement('hidden', 'id', $this->_customdata['cm']->id);
        $mform->setType("id", PARAM_INT);

        foreach ($this->_customdata['tasks'] as $task) {
            $taskprefix = "task_$index";

            $mform->addElement('header', "{$taskprefix}_header", $task->name);

            // HTML display of description.
            if (!empty($task->description)) {
                $mform->addElement('html', '<div><strong>Description</strong><pre>' . s($task->description) . '</pre></div>');
            }
            
            if (!empty($task->id)) {
                $mform->addElement('html', '<div><strong>Task ID</strong><pre>' . s($task->id) . '</pre></div>');
            }
            
            if (!empty($task->scenarioTemplateId)) {
                $mform->addElement('html', '<div><strong>Scenario Template ID</strong><pre>' . s($task->scenarioTemplateId) . '</pre></div>');
            }            
            
            if (!empty($task->vmMask)) {
                $mform->addElement('html', '<div><strong>VM Mask</strong><pre>' . s($task->vmMask) . '</pre></div>');
            }
            
            if (!empty($task->inputString)) {
                $mform->addElement('html', '<div><strong>Input String</strong><pre>' . s($task->inputString) . '</pre></div>');
            }
            
            if (!empty($task->expectedOutput)) {
                $mform->addElement('html', '<div><strong>Expected Output</strong><pre>' . s($task->expectedOutput) . '</pre></div>');
            }            

            // Hidden fields to track values.
            $mform->addElement('hidden', "{$taskprefix}_name", $task->name);
            $mform->setType("{$taskprefix}_name", PARAM_RAW);

            $mform->addElement('hidden', "{$taskprefix}_description", $task->description);
            $mform->setType("{$taskprefix}_description", PARAM_RAW);

            $mform->addElement('hidden', "{$taskprefix}_dispatchtaskid", $task->id);
            $mform->setType("{$taskprefix}_dispatchtaskid", PARAM_ALPHANUMEXT);

            $mform->addElement('hidden', "{$taskprefix}_scenariotemplateid", $task->scenarioTemplateId);
            $mform->setType("{$taskprefix}_scenariotemplateid", PARAM_ALPHANUMEXT);

            // Input fields per task.
            $mform->addElement('checkbox', "{$taskprefix}_visible", get_string('visible', 'crucible'));
            $mform->addElement('checkbox', "{$taskprefix}_gradable", get_string('gradable', 'crucible'));
            $mform->addElement('checkbox', "{$taskprefix}_multiple", get_string('multiple', 'crucible'));
            $mform->addElement('html', '<div><strong>' . get_string('points', 'crucible') . '</strong></div>');
            $mform->addElement('text', "{$taskprefix}_points", '', ['size' => '5']);
            $mform->setType("{$taskprefix}_points", PARAM_INT);
            $mform->disabledIf("{$taskprefix}_points", "{$taskprefix}_gradable");

            // Help Buttons
            $mform->addHelpButton("{$taskprefix}_visible", 'visible', 'crucible');
            $mform->addHelpButton("{$taskprefix}_gradable", 'gradable', 'crucible');
            $mform->addHelpButton("{$taskprefix}_multiple", 'multiple', 'crucible');
            $mform->addHelpButton("{$taskprefix}_points", 'points', 'crucible');

            // Set defaults based on DB record.
            $rec = $DB->get_record_sql(
                'SELECT * FROM {crucible_tasks} WHERE ' . $DB->sql_compare_text('dispatchtaskid') . ' = ' . $DB->sql_compare_text(':dispatchtaskid'),
                ['dispatchtaskid' => $task->id]
            );

            if ($rec) {
                $mform->setDefault("{$taskprefix}_visible", $rec->visible);
                $mform->setDefault("{$taskprefix}_gradable", $rec->gradable);
                $mform->setDefault("{$taskprefix}_multiple", $rec->multiple);
                $mform->setDefault("{$taskprefix}_points", $rec->points);
            } else {
                $mform->setDefault("{$taskprefix}_visible", 1);
                $mform->setDefault("{$taskprefix}_gradable", 1);
                $mform->setDefault("{$taskprefix}_multiple", 1);
                $mform->setDefault("{$taskprefix}_points", 1);
            }

            $index++;
        }
        $this->add_action_buttons();

    }

    /**
     * Validates submitted form data.
     *
     * @param array $data  Submitted form values.
     * @param array $files Submitted files.
     * @return array An array of error messages, or an empty array if none.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

    }

    /**
     * Preprocesses data before it's set in the form.
     *
     * @param array $data The form data to preprocess.
     */
    public function data_preprocessing(&$data) {

    }

    /**
     * Processes form data after it has been submitted.
     *
     * @param array $data The submitted form data to postprocess.
     */
    public function data_postprocessing(&$data) {
        // TODO save tasks to the db.

        // TODO if grade method changed, update all grades.
    }


}

