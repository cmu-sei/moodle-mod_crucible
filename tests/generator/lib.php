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

defined('MOODLE_INTERNAL') || die();

/**
 * Crucible module data generator.
 *
 * Supplies the fields the activity form would normally fill in, so tests can create a
 * Crucible activity without the form, which needs a live Alloy to list event templates.
 *
 * @package    mod_crucible
 * @category   test
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_crucible_generator extends testing_module_generator {
    /**
     * Create a Crucible activity instance.
     *
     * @param array|stdClass|null $record Data for the instance.
     * @param array|null $options General options for course module.
     * @return stdClass The record from the crucible table, with extra cmid field.
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;

        $defaults = [
            'eventtemplateid' => '00000000-0000-0000-0000-000000000000',
            'vmapp' => 1,
            'clock' => 1,
            'extendevent' => 0,
            'extendinterval' => 60,
            'allowstudentinvites' => 1,
            'showcontentlicense' => 0,
            'grademethod' => 0,
            'timeopen' => 0,
            'timeclose' => 0,
        ];
        foreach ($defaults as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        return parent::create_instance($record, (array)$options);
    }
}
