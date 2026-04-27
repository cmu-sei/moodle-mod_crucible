<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Restore settings for crucible activity module.
 *
 * @package    mod_crucible
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Crucible restore settings
 *
 * Allows admin to remap event templates during restore if the original
 * template doesn't exist in the target Alloy instance.
 */
class restore_crucible_activity_structure_step_extended extends restore_crucible_activity_structure_step {

    /**
     * Define structure with template validation
     */
    protected function define_structure() {
        $paths = parent::define_structure();

        // Add after-restore hook to validate/remap templates
        $this->add_after_restore_hook('check_event_templates');

        return $paths;
    }

    /**
     * After restore hook - check if event templates exist
     */
    protected function check_event_templates() {
        global $DB;

        // Get all crucible activities that were just restored in this course
        $courseid = $this->get_courseid();
        $crucibles = $DB->get_records('crucible', ['course' => $courseid]);

        require_once($CFG->dirroot . '/mod/crucible/locallib.php');

        foreach ($crucibles as $crucible) {
            // Check if template exists in Alloy
            $templateexists = crucible_validate_eventtemplate($crucible->eventtemplateid);

            if (!$templateexists) {
                // Log warning
                $this->log(
                    'Event template ' . $crucible->eventtemplateid . ' not found for activity "' . $crucible->name . '"',
                    backup::LOG_WARNING
                );

                // TODO: Could set a flag on the activity to show warning in UI
                // $crucible->introformat = -1; // Special flag
                // $DB->update_record('crucible', $crucible);
            }
        }
    }
}
