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

use mod_crucible\crucible;

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->dirroot/mod/crucible/lib.php");
require_once("$CFG->dirroot/mod/crucible/locallib.php");

$id = optional_param('id', 0, PARAM_INT); // Course_module ID.
$c = optional_param('c', 0, PARAM_INT);  // Instance ID - it should be named as the first character of the module.

try {
    if ($id) {
        $cm         = get_coursemodule_from_id('crucible', $id, 0, false, MUST_EXIST);
        $course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $crucible   = $DB->get_record('crucible', ['id' => $cm->instance], '*', MUST_EXIST);
    } else if ($c) {
        $crucible   = $DB->get_record('crucible', ['id' => $c], '*', MUST_EXIST);
        $course     = $DB->get_record('course', ['id' => $crucible->course], '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('crucible', $crucible->id, $course->id, false, MUST_EXIST);
    }
} catch (Exception $e) {
    throw new moodle_exception('invalidcoursemodule', 'error');
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/crucible:manage', $context);

// Print the page header.
$url = new moodle_url ( '/mod/crucible/tasks.php', ['id' => $cm->id]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string("Manage Tasks"));
$PAGE->set_heading($course->fullname);

// New crucible class.
$pageurl = $url;
$pagevars = [];
$object = new \mod_crucible\crucible($cm, $course, $crucible, $pageurl, $pagevars);

// Get eventtemplate info.
$object->eventtemplate = get_eventtemplate($object->userauth, $crucible->eventtemplateid);

// Update the database.
if ($object->eventtemplate) {
    $scenariotemplateid = $object->eventtemplate->scenarioTemplateId;
    // Update the database.
    $crucible->name = $object->eventtemplate->name;
    $crucible->intro = $object->eventtemplate->description;
    $DB->update_record('crucible', $crucible);
    // This generates lots of hvp module errors.
    // rebuild_course_cache($crucible->course);
} else {
    throw new moodle_exception(null, '', '', null, 'Invalid event template');
}

$renderer = $PAGE->get_renderer('mod_crucible');
echo $renderer->header();

$renderer->display_detail($crucible, $object->eventtemplate->durationHours);

$tasks = get_scenariotemplatetasks($object->userauth, $scenariotemplateid);
if (is_null($tasks)) {
    $tasks = get_scenariotemplatetasks($object->systemauth, $scenariotemplateid);
}

if (!empty($tasks) && is_array($tasks)) {
    usort($tasks, "tasksort");

    $mform = new \mod_crucible\crucible_tasks_form(null, ['tasks' => $tasks, 'cm' => $cm]);

    // Form processing and displaying is done here.
    if ($mform->is_cancelled()) {
        redirect(new moodle_url('/mod/crucible/view.php', ["id" => $id]));
    } else if ($fromform = $mform->get_data()) {
        foreach ($fromform as $task) {
            if (is_array($task)) {
                // Process each task (same logic as before).
                $rec = $DB->get_record_sql('SELECT * from {crucible_tasks} WHERE '
                        . $DB->sql_compare_text('dispatchtaskid') . ' = '
                        . $DB->sql_compare_text(':dispatchtaskid'), ['dispatchtaskid' => $task['dispatchtaskid']]);
                if ($rec === false) {
                    $task['crucibleid'] = $crucible->id;
                    debugging("creating task record for " . $task['dispatchtaskid'], DEBUG_DEVELOPER);
                    $DB->insert_record('crucible_tasks', $task);
                } else if ($rec) {
                    if ($task['visible']) {
                        $rec->visible = $task['visible'];
                    } else {
                        $rec->visible = 0;
                    }
                    if ($task['gradable']) {
                        $rec->gradable = $task['gradable'];
                        if ($task['points']) {
                            $rec->points = $task['points'];
                        } else {
                            $rec->points = 1;
                        }
                    } else {
                        $rec->gradable = 0;
                        $rec->points = 0;
                    }
                    if ($task['multiple']) {
                        $rec->multiple = $task['multiple'];
                    } else {
                        $rec->multiple = 0;
                    }
                    debugging("updating task record for " . $task['dispatchtaskid'], DEBUG_DEVELOPER);
                    $DB->update_record('crucible_tasks', $rec);
                }
            }
        }
        $mform->display();
    } else {
        $mform->display();
    }
} else {
    \core\notification::warning(get_string('notasksavailable', 'mod_crucible'));

    // Redirect button back to Crucible activity view.
    $backurl = new moodle_url('/mod/crucible/view.php', ['id' => $cm->id]);
    echo $OUTPUT->single_button($backurl, get_string('backtocruclanding', 'mod_crucible'));
}

echo $renderer->footer();


