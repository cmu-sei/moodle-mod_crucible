<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Restore decode content for crucible activity module.
 *
 * @package    mod_crucible
 * @copyright  2024 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restore decode content class for crucible activity.
 */
class restore_crucible_decode_content extends restore_decode_content {

    /**
     * Get the mapping for event template IDs during restore.
     *
     * This allows remapping old event template IDs to new ones
     * when restoring across different Alloy environments.
     *
     * @return array Array of mappings
     */
    protected function define_decode_contents() {
        $contents = array();

        // Decode event template IDs in crucible table
        $contents[] = new restore_decode_rule('CRUCIBLEEVENTTEMPLATE', '/\$@CRUCIBLEEVENTTEMPLATE\*([0-9]+)@\$/', 'crucible');

        return $contents;
    }
}
