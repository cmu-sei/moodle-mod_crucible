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

namespace mod_crucible\utils;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the grading scale type helper.
 *
 * @package    mod_crucible
 * @category   test
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_crucible\utils\scaletypes::class)]
final class scaletypes_test extends \basic_testcase {

    /**
     * The names are what settings and forms store, so they are part of the plugin's
     * saved data: renaming a key or repointing it at a different constant silently
     * changes how existing activities are graded.
     */
    public function test_get_types_names_map_to_constants(): void {
        $expected = [
            'firstattempt' => scaletypes::CRUCIBLE_FIRSTATTEMPT,
            'lastattempt' => scaletypes::CRUCIBLE_LASTATTEMPT,
            'average' => scaletypes::CRUCIBLE_ATTEMPTAVERAGE,
            'highestgrade' => scaletypes::CRUCIBLE_HIGHESTATTEMPTGRADE,
        ];

        $this->assertSame($expected, scaletypes::get_types());
    }

    /**
     * The display array is keyed by the stored constant value because forms use it as
     * a select menu: the keys are the values that end up in the database.
     */
    public function test_get_display_types_is_keyed_by_constant(): void {
        $expected = [
            scaletypes::CRUCIBLE_FIRSTATTEMPT,
            scaletypes::CRUCIBLE_LASTATTEMPT,
            scaletypes::CRUCIBLE_ATTEMPTAVERAGE,
            scaletypes::CRUCIBLE_HIGHESTATTEMPTGRADE,
        ];

        $this->assertSame($expected, array_keys(scaletypes::get_display_types()));
    }

    /**
     * Every label has to resolve to a real string in the lang pack. A missing string
     * still renders, as [[somekey]], so only asserting non-empty would let it through.
     */
    public function test_get_display_types_labels_come_from_the_lang_pack(): void {
        foreach (scaletypes::get_display_types() as $value => $label) {
            $this->assertIsString($label, "label for scale type $value");
            $this->assertNotSame('', $label, "label for scale type $value");
            $this->assertFalse(str_starts_with($label, '[['), "missing lang string for scale type $value: $label");
        }
    }

    /**
     * The two arrays are two views of one list. If a type is added to only one of them,
     * either the setting cannot be displayed or the menu offers a value nothing accepts.
     */
    public function test_both_arrays_describe_the_same_types(): void {
        $values = array_values(scaletypes::get_types());
        $displaykeys = array_keys(scaletypes::get_display_types());

        sort($values);
        sort($displaykeys);

        $this->assertSame($values, $displaykeys);
    }
}
