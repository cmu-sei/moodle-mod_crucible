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

namespace mod_crucible;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/crucible/lib.php');

/**
 * Unit tests for the mod_crucible lib.php callbacks.
 *
 * @package    mod_crucible
 * @category   test
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('crucible_supports')]
#[\PHPUnit\Framework\Attributes\CoversFunction('crucible_get_extra_capabilities')]
final class lib_test extends \basic_testcase {

    /**
     * The features the module claims are the ones Moodle acts on: intro, completion
     * view tracking, grades and backup all change how core treats the activity.
     *
     * @param string $feature FEATURE_* constant.
     * @param mixed $expected What crucible_supports() should answer.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('supports_provider')]
    public function test_crucible_supports(string $feature, $expected): void {
        $this->assertSame($expected, crucible_supports($feature));
    }

    /**
     * Feature answers this module is expected to give.
     *
     * @return array
     */
    public static function supports_provider(): array {
        return [
            'archetype' => [FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER],
            'groups' => [FEATURE_GROUPS, false],
            'groupings' => [FEATURE_GROUPINGS, false],
            'intro' => [FEATURE_MOD_INTRO, true],
            'completion tracks views' => [FEATURE_COMPLETION_TRACKS_VIEWS, true],
            'has grade' => [FEATURE_GRADE_HAS_GRADE, true],
            'grade outcomes' => [FEATURE_GRADE_OUTCOMES, false],
            'backup' => [FEATURE_BACKUP_MOODLE2, true],
            'show description' => [FEATURE_SHOW_DESCRIPTION, true],
        ];
    }

    /**
     * An unknown feature has to come back null, not false: core treats false as
     * "explicitly unsupported" and null as "no opinion".
     */
    public function test_unknown_feature_is_undecided(): void {
        $this->assertNull(crucible_supports('mod_crucible_not_a_feature'));
    }

    public function test_extra_capabilities(): void {
        $this->assertSame(['moodle/site:accessallgroups'], crucible_get_extra_capabilities());
    }
}
