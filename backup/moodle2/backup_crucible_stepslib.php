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
 * Define all the backup steps that will be used by the backup_crucible_activity_task
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

 /**
  * Define the complete crucible structure for backup, with file and id annotations
  */
class backup_crucible_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines the structure of the crucible activity for backup.
     *
     * @return backup_nested_element The root element containing all structure elements.
     */
    protected function define_structure() {

        // The crucible module stores no user info.

        // Define each element separated.
        $crucible = new backup_nested_element('crucible', ['id'], [
            'name', 'intro', 'introformat', 'eventtemplateid', 'vmapp',
            'clock', 'extendevent', 'timeopen', 'timeclose', 'grade',
            'grademethod', 'timecreated', 'timemodified']);

        // Define sources.
        $crucible->set_source_table('crucible', ['id' => backup::VAR_ACTIVITYID]);

        // Define file annotations.
        $crucible->annotate_files('mod_crucible', 'intro', null); // This file area hasn't itemid.

        // Return the root element (crucible), wrapped into standard activity structure.
        return $this->prepare_activity_structure($crucible);

    }
}
