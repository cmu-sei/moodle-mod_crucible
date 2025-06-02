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

            $group = [];

            $mform->addElement('header', 'task', $task->name);
            $group[] = $mform->createElement('hidden', 'name', $task->name);

            $mform->addElement('html', '<div>Description<pre>' . $task->description  . '</pre></div>');
            $group[] = $mform->createElement('hidden', 'description', $task->description);

            $mform->addElement('html', '<div>Task ID<pre>' . $task->id . '</pre></div>');
            $group[] = $mform->createElement('hidden', 'dispatchtaskid', $task->id);

            $mform->addElement('html', '<div>Scenario Template ID<pre>' . $task->scenarioTemplateId. '</pre></div>');
            $group[] = $mform->createElement('hidden', 'scenariotemplateid', $task->scenarioTemplateId);

            $mform->addElement('html', '<div>VM Mask<pre>' . $task->vmMask. '</pre></div>');
            $input = isset($task->inputString) ? $task->inputString : '';
            $mform->addElement('html', '<div>Input String<pre>' . $input . '</pre></div>');
            $mform->addElement('html', '<div>Expected Output<pre>' . $task->expectedOutput . '</pre></div>');

            $group[] = $mform->createElement('checkbox', 'visible', get_string('visible', 'crucible'));
            // $mform->addHelpButton('visible', 'visiblehelp', 'crucible');

            $group[] = $mform->createElement('checkbox', 'gradable', get_string('gradable', 'crucible'));
            // $mform->addHelpButton('gradble', 'gradablehelp', 'crucible');

            $group[] = $mform->createElement('checkbox', 'multiple', get_string('multiple', 'crucible'));
            // $mform->addHelpButton('mutiple', 'multiplehelp', 'crucible');

            $group[] = $mform->createElement('text', 'points', get_string('points', 'crucible'), ['size' => '5']);
            // $mform->addHelpButton('points', 'pointshelp', 'crucible');

            $group[] = $mform->createElement('html', get_string('points', 'crucible'));

            $mform->addGroup($group, "options-$index", 'Options', [' '], true);

            $mform->setType("options-" . $index . "[name]", PARAM_RAW);
            $mform->setType("options-" . $index . "[description]", PARAM_RAW);
            $mform->setType("options-" . $index . "[dispatchtaskid]", PARAM_ALPHANUMEXT);
            $mform->setType("options-" . $index . "[scenariotemplateid]", PARAM_ALPHANUMEXT);
            $mform->setType("options-" . $index . "[points]", PARAM_INT);

            $rec = $DB->get_record_sql('SELECT * from {crucible_tasks} WHERE '
                    . $DB->sql_compare_text('dispatchtaskid') . ' = '
                    . $DB->sql_compare_text(':dispatchtaskid'), ['dispatchtaskid' => $task->id]);

            if ($rec === false) {
                $mform->setDefault("options-" . $index . "[visible]", 1);
                $mform->setDefault("options-" . $index . "[gradable]", 1);
                $mform->setDefault("options-" . $index . "[multiple]", 1);
                $mform->setDefault("options-" . $index . "[points]", 1);
            } else {
                $mform->setDefault("options-" . $index . "[visible]", $rec->visible);
                $mform->setDefault("options-" . $index . "[gradable]", $rec->gradable);
                $mform->setDefault("options-" . $index . "[multiple]", $rec->multiple);
                $mform->setDefault("options-" . $index . "[points]", $rec->points);
            }

            $mform->disabledIf("options-" . $index . "[points]", "options-" .$index . "[gradable]");

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

