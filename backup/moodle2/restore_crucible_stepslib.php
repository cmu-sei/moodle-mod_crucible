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
 * Step definitions for restoring Crucible activity module.
 *
 * @package   mod_crucible
 * @category  backup
 * @copyright ...
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_crucible_activity_task
 */

/**
 * Structure step to restore one crucible activity
 */
class restore_crucible_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define the structure to be restored for the Crucible activity.
     *
     * @return array List of restore path elements.
     */
    protected function define_structure() {

        $paths = [];
        $paths[] = new restore_path_element('crucible', '/activity/crucible');

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Processes the restored data for a Crucible activity instance.
     *
     * @param stdClass $data The data from the backup to restore.
     */
    protected function process_crucible($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Insert the Crucible record into the database.
        $newitemid = $DB->insert_record('crucible', $data);

        // Register this instance for linking with the course module.
        $this->apply_activity_instance($newitemid);
    }


    /**
     * Performs final steps after the Crucible activity restore has been executed.
     *
     * This includes restoring any files related to the module (e.g., intro attachments).
     */
    protected function after_execute() {
        // Add Crucible related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_crucible', 'intro', null);
    }

}
