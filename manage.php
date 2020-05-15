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
 * crucible module main user interface
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

use \mod_crucible\crucible;

//require('../../config.php');
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->dirroot/mod/crucible/lib.php");
require_once("$CFG->dirroot/mod/crucible/locallib.php");
require_once($CFG->libdir . '/completionlib.php');

$c = optional_param('c', 0, PARAM_INT);

try {
    $course = $DB->get_record('course', array('id' => $c), '*', MUST_EXIST);
} catch (Exception $e) {
    print_error("invalid course id passed");
}

$context = context_course::instance($course->id);
require_capability('mod/crucible:manage', $context);

// Print the page header.
$url = new moodle_url ('/mod/crucible/manage.php');

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string("Manage Crucible"));
$PAGE->set_heading($course->fullname);

// new crucible class
$pageurl = $url;
$pagevars = array();

$isinstructor = has_capability('mod/crucible:manage', $context);

if (!$isinstructor) {
    print_error('you do not have access to the page');
}

$renderer = $PAGE->get_renderer('mod_crucible');
echo $renderer->header();

// get all attempts for all activities in this course

$attempts = getall_course_attempts($course->id);
//$attempts = getall_crucible_attempts();

echo $renderer->display_attempts($attempts, $showgrade = true, $showuser = true, $showdetail = true);

echo $renderer->footer();


