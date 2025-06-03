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

namespace mod_crucible\utils;

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
 * Class to define grade types for the module
 * Is used in multiple classes/functions
 *
 * @package     mod_crucible
 * @copyright   2020 Carnegie Mellon University
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scaletypes {

    /** Define grading scale types */
    /** @var int Grading method: use score from the first attempt. */
    const CRUCIBLE_FIRSTATTEMPT = 1;

    /** @var int Grading method: use score from the last attempt. */
    const CRUCIBLE_LASTATTEMPT = 2;

    /** @var int Grading method: use average score across all attempts. */
    const CRUCIBLE_ATTEMPTAVERAGE = 3;

    /** @var int Grading method: use the highest score from any attempt. */
    const CRUCIBLE_HIGHESTATTEMPTGRADE = 4;


    /**
     * Return array of scale types keyed by the type name
     *
     * @return array
     */
    public static function get_types() {

        return [
            'firstattempt' => self::CRUCIBLE_FIRSTATTEMPT,
            'lastattempt'  => self::crucible_LASTATTEMPT,
            'average'      => self::CRUCIBLE_ATTEMPTAVERAGE,
            'highestgrade' => self::CRUCIBLE_HIGHESTATTEMPTGRADE,
        ];
    }

    /**
     * Returns an array of scale types for display, i.e. a form
     * keyed by the values that each type is
     *
     * @return array
     */
    public static function get_display_types() {

        return [
            self::CRUCIBLE_FIRSTATTEMPT        => get_string('firstattempt', 'crucible'),
            self::CRUCIBLE_LASTATTEMPT       => get_string('lastattempt', 'crucible'),
            self::CRUCIBLE_ATTEMPTAVERAGE      => get_string('attemptaverage', 'crucible'),
            self::CRUCIBLE_HIGHESTATTEMPTGRADE => get_string('highestattempt', 'crucible'),
        ];
    }

}


