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

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/crucible/locallib.php');
require_once("$CFG->dirroot/lib/licenselib.php");

/**
 * Form definition for creating or editing Crucible activity instances.
 *
 * @package    mod_crucible
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_crucible_mod_form extends moodleform_mod {

    /** @var array options to be used with date_time_selector fields in the activity. */
    public static $datefieldoptions = ['optional' => true];

    /** @var array|null List of event templates fetched from Alloy. */
    protected $eventtemplates = null;

    /**
     * Defines the form elements for the Crucible activity module.
     *
     * This method builds the Moodle form used for configuring an instance
     * of the Crucible activity, including general settings, appearance,
     * timing, grading, and completion.
     */
    public function definition() {
        global $COURSE, $CFG, $DB, $PAGE;
        $mform = $this->_form;

        $config = get_config('crucible');

        // Adding the standard "intro" and "introformat" fields.
        $this->standard_intro_elements();
        // TODO remove ability to edit the description and just show the select and dropdown.
        // $mform->removeElement('introeditor');
        // TODO figure out why the description doesnt appear.
        // $mform->removeElement('showdescription');

        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Pull list from alloy.
        $systemauth = setup();
        $this->eventtemplates = get_eventtemplates($systemauth);
        $labnames = [];
        $labs = [];
        foreach ($this->eventtemplates as $eventtemplate) {
            array_push($labnames, $eventtemplate->name);
            $labs[$eventtemplate->id] = s($eventtemplate->name);
        }
        array_unshift($labs, "");
        asort($labs);

        $options = [
            'multiple' => false,
            // 'noselectionstring' => get_string('selectname', 'crucible'),
            'placeholder' => get_string('selectname', 'crucible'),
        ];
        if ($config->autocomplete) {
            $mform->addElement('autocomplete', 'eventtemplateid', get_string('eventtemplate', 'crucible'), $labs, $options);
        } else {
            $mform->addElement('select', 'eventtemplateid', get_string('eventtemplate', 'crucible'), $labs);
        }

        $mform->addRule('eventtemplateid', null, 'required', null, 'client');
        $mform->addRule('eventtemplateid', 'You must choose an option', 'minlength', '2', 'client'); // Why is this client?

        $mform->setDefault('eventtemplateid', null);
        $mform->addHelpButton('eventtemplateid', 'eventtemplate', 'crucible');

        $licenses = license_manager::get_licenses();
        if ($licenses) {
            foreach ($licenses as $license) {
                $license_options[$license->shortname] = $license->fullname;
            }
        } else {
            debugging('No licenses found.', DEBUG_DEVELOPER);
        }

        $mform->addElement('select', 'contentlicense', get_string('contentlicense', 'crucible'), $license_options);
        $mform->setType('contentlicense', PARAM_TEXT);
        $mform->addHelpButton('contentlicense', 'contentlicense', 'crucible');
        
        $mform->addElement('checkbox', 'showcontentlicense', get_string('showcontentlicense', 'crucible'));
        $mform->addHelpButton('showcontentlicense', 'showcontentlicense', 'crucible');

        $mform->addElement('header', 'optionssection', get_string('appearance'));

        $options = ['Display Link to Player', 'Embed VM App'];
        $mform->addElement('select', 'vmapp', get_string('vmapp', 'crucible'), $options);
        $mform->setDefault('vmapp', $config->vmapp);
        $mform->addHelpButton('vmapp', 'vmapp', 'crucible');

        $options = ['Hidden', 'Countdown', 'Timer'];
        $mform->addElement('select', 'clock', get_string('clock', 'crucible'), $options);
        $mform->setDefault('clock', 1); // Set default to the index of 'Countdown' in $options array.
        $mform->addHelpButton('clock', 'clock', 'crucible');

        // Grade settings.
        $this->standard_grading_coursemodule_elements();

        $mform->removeElement('grade');
        $currentgrade = 0;
        if (property_exists($this->current, 'grade')) {
            $currentgrade = $this->current->grade;
        }

        $mform->addElement('text', 'grade', $currentgrade);
        $mform->setType('grade', PARAM_FLOAT);
        // $mform->addHelpButton('grade', 'grade', 'crucible');

        $mform->addElement('select', 'grademethod',
            get_string('grademethod', 'crucible'),
            \mod_crucible\utils\scaletypes::get_display_types());
        $mform->setType('grademethod', PARAM_INT);
        $mform->addHelpButton('grademethod', 'grademethod', 'crucible');
        // $mform->hideIf('grademethod', 'grade', 'eq', '0');

        $mform->addElement('header', 'timing', get_string('timing', 'crucible'));

        // Open and close dates.
        $mform->addElement('date_time_selector', 'timeopen', get_string('eventopen', 'crucible'),
                self::$datefieldoptions);
        $mform->addHelpButton('timeopen', 'eventopen', 'crucible');

        $mform->addElement('date_time_selector', 'timeclose', get_string('eventclose', 'crucible'),
                self::$datefieldoptions);
        $mform->addHelpButton('timeclose', 'eventclose', 'crucible');

        $mform->addElement('checkbox', 'extendevent', get_string('extendeventsetting', 'crucible'));
        $mform->addHelpButton('extendevent', 'extendeventsetting', 'crucible');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();

    }

    /**
     * Validates form input data.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array An array of error messages, or an empty array if none.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check open and close times are consistent.
        if ($data['timeopen'] != 0 && $data['timeclose'] != 0 &&
                $data['timeclose'] < $data['timeopen']) {
            $errors['timeclose'] = get_string('closebeforeopen', 'quiz');
        }

        if (array_key_exists('completion', $data) && $data['completion'] == COMPLETION_TRACKING_AUTOMATIC) {
            $completionpass = isset($data['completionpass']) ? $data['completionpass'] : $this->current->completionpass;

            // Show an error if require passing grade was selected and the grade to pass was set to 0.
            if ($completionpass && (empty($data['gradepass']) || grade_floatval($data['gradepass']) == 0)) {
                if (isset($data['completionpass'])) {
                    $errors['completionpassgroup'] = get_string('gradetopassnotset', 'crucible');
                } else {
                    $errors['gradepass'] = get_string('gradetopassmustbeset', 'crucible');
                }
            }
        }
    }

    /**
     * Preprocesses form data before displaying it in the form.
     *
     * @param array $data The form data to preprocess.
     */
    public function data_preprocessing(&$data) {

        // Completion settings check.
        if (empty($toform['completionusegrade'])) {
            $toform['completionpass'] = 0; // Forced unchecked.
        }

    }

    /**
     * Postprocesses form data after it has been submitted.
     *
     * @param stdClass $data The submitted form data object.
     * @return void
     */
    public function data_postprocessing($data) {
        if (!$data->eventtemplateid) {
            echo "return to settings page<br>";
            exit;
        }
        if (!$data->vmapp) {
            $data->vmapp = 0;
        }
        $index = array_search($data->eventtemplateid, array_column($this->eventtemplates, 'id'), true);
        $data->name = $this->eventtemplates[$index]->name;
        $rawdescription = $this->eventtemplates[$index]->description;
        $data->intro = strip_tags($rawdescription); // Removes all HTML tags.
        $data->introformat = FORMAT_PLAIN;

        if (!isset($data->showcontentlicense)) {
            $data->showcontentlicense = 0; // Checkbox unchecked, set to 0.
        }

        // TODO if grade method changed, update all grades.
    }


}

