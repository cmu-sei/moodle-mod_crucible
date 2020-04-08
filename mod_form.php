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

defined('MOODLE_INTERNAL') || die;

require_once ($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/crucible/locallib.php');

class mod_crucible_mod_form extends moodleform_mod {

    function eventtemplate() {
        global $COURSE, $CFG, $DB, $PAGE;
        $mform = $this->_form;

        $config = get_config('crucible');

        //-------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

	// pull list of exercises/labs from alloy
        $this->eventtemplates = get_eventtemplates();
        $labnames = array();
	$labs = [];
        foreach ($this->eventtemplates as $eventtemplate) {
            array_push($labnames, $eventtemplate->name);
            $labs[$eventtemplate->id] = s($eventtemplate->name);
        }
	array_unshift($labs, "");
	asort($labs);

	$options = array(
	    'multiple' => false,
	    //'noselectionstring' => get_string('selectname', 'crucible'),
	    'placeholder' => get_string('selectname', 'crucible')
	);
	if ($config->autocomplete) {
            $mform->addElement('autocomplete', 'eventtemplate', get_string('eventtemplate', 'crucible'), $labs, $options);
	} else {
            $mform->addElement('select', 'eventtemplate', get_string('eventtemplate', 'crucible'), $labs);
	}

        $mform->addRule('eventtemplate', null, 'required', null, 'client');
        $mform->addRule('eventtemplate', 'You must choose an option', 'minlength', '2', 'client');

        $mform->setDefault('eventtemplate', null);
        $mform->addHelpButton('eventtemplate', 'eventtemplate', 'crucible');

        //-------------------------------------------------------
        $mform->addElement('header', 'optionssection', get_string('appearance'));

	$options = array('Display Link to Player', 'Embed VM App');
        $mform->addElement('select', 'vmapp', get_string('vmapp', 'crucible'), $options);
        $mform->setDefault('vmapp', $config->vmapp);
        $mform->addHelpButton('vmapp', 'vmapp', 'crucible');

        $options = array('', 'Countdown', 'Timer');
        $mform->addElement('select', 'clock', get_string('clock', 'crucible'), $options);
        $mform->setDefault('clock', '');
        $mform->addHelpButton('clock', 'clock', 'crucible');


        // Grade settings.
        $this->standard_grading_coursemodule_elements();


        //-------------------------------------------------------
        $this->standard_coursemodule_elements();

        //-------------------------------------------------------
        $this->add_action_buttons();

    }

    function data_preprocessing(&$data) {
    }

    function data_postprocessing(&$data) {
	if (!$data->eventtemplate) {
            echo "return to settings page<br>";
	    exit;
        }
	if (!$data->vmapp) {
	    $data->vmapp = 0;
        }
	$index = array_search($data->eventtemplate, array_column($this->eventtemplates, 'id'), true);
	$data->name = $this->eventtemplates[$index]->name;
        $data->intro = $this->eventtemplates[$index]->description;
    }


}

