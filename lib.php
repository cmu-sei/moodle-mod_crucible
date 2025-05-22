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
 * Crucible mod callbacks.
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

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();

/**
 * List of features supported in crucible module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function crucible_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:           return MOD_ARCHETYPE_OTHER;
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}
/**
 * Returns all other caps used in module
 * @return array
 */
function crucible_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function crucible_reset_userdata($data) {

    // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
    // See MDL-9367.

    return array();
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function crucible_get_post_actions() {
    return array('update', 'add');
}

/**
 * Add crucible instance.
 * @param object $crucible
 * @param object $mform
 * @return int new crucible instance id
 */
function crucible_add_instance($crucible, $mform) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/crucible/locallib.php');

    $cmid = $crucible->coursemodule;

    $result = crucible_process_options($crucible);
    if ($result && is_string($result)) {
        return $result;
    }

    $crucible->created = time();
    $crucible->grade = 100; //default
    $crucible->id = $DB->insert_record('crucible', $crucible);

    // Do the processing required after an add or an update.
    crucible_after_add_or_update($crucible);

    return $crucible->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * Update crucible instance.
 * @param object $crucible
 * @param object $mform
 * @return bool true
 */
function crucible_update_instance(stdClass $crucible, $mform) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/crucible/locallib.php');

    // Process the options from the form.
    $result = crucible_process_options($crucible);
    if ($result && is_string($result)) {
        return $result;
    }
    // Get the current value, so we can see what changed.
   // $oldcrucible = $DB->get_record('crucible', array('id' => $crucible->instance));

    // Update the database.
    $crucible->id = $crucible->instance;
    $DB->update_record('crucible', $crucible);

    // Do the processing required after an add or an update.
    crucible_after_add_or_update($crucible);

    // Do the processing required after an add or an update.
    return true;

}

/**
 * This function is called at the end of quiz_add_instance
 * and quiz_update_instance, to do the common processing.
 *
 * @param object $quiz the quiz object.
 */
function crucible_after_add_or_update($crucible) {
    global $DB;
    $cmid = $crucible->coursemodule;

    // We need to use context now, so we need to make sure all needed info is already in db.
    $DB->set_field('course_modules', 'instance', $crucible->id, array('id'=>$cmid));
    $context = context_module::instance($cmid);

    // Update related grade item.
    crucible_grade_item_update($crucible);
}


function crucible_process_options($crucible) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/crucible/locallib.php');
    $crucible->timemodified = time();

}

/**
 * Delete crucible instance.
 * @param int $id
 * @return bool true
 */
function crucible_delete_instance($id) {
    global $DB;
    $crucible = $DB->get_record('crucible', array('id' => $id), '*', MUST_EXIST);

    // delete calander events
    $events = $DB->get_records('event', array('modulename' => 'crucible', 'instance' => $crucible->id));
    foreach ($events as $event) {
        $event = calendar_event::load($event);
        $event->delete();
    }


    // delete grade from database
    crucible_grade_item_delete($crucible);

    // note: all context files are deleted automatically

    $DB->delete_records('crucible', array('id'=>$crucible->id));

    return true;
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 *
 * See {@link get_array_of_activities()} in course/lib.php
 *
 * @param object $coursemodule
 * @return cached_cm_info info
 */
function crucible_get_coursemodule_info($coursemodule) {
}

/*
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $crucible        crucible object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function crucible_view($crucible, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $crucible->id
    );

    $event = \mod_crucible\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('crucible', $crucible);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function crucible_check_updates_since(cm_info $cm, $from, $filter = array()) {
    $updates = course_check_module_updates_since($cm, $from, array('content'), $filter);
    return $updates;
}
/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_crucible_core_calendar_provide_event_action(calendar_event $event,
                                                       \core_calendar\action_factory $factory) {
    $cm = get_fast_modinfo($event->courseid)->instances['crucible'][$event->instance];

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new \moodle_crucible('/mod/crucible/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

/**
 * Update grades in central gradebook
 *
 * @category grade
 * @param object $crucible the crucible settings.
 * @param int $userid specific user only, 0 means all users.
 * @param bool $nullifnone If a single user is specified and $nullifnone is true a grade item with a null rawgrade will be inserted
 */
function crucible_update_grades($crucible, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    $grades = array();
    foreach ($userid as $user) {
        $rawgrade = crucible_get_user_grades($crucible, $userid);
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = $rawgrade;
        $grades[$user] = $grade;
    } 
    return crucible_grade_item_update($crucible, $grades);
}

/**
 * Create or update the grade item for given lab
 *
 * @category grade
 * @param object $crucible object with extra cmidnumber
 * @param mixed $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function crucible_grade_item_update($crucible, $grades = null) {
    global $CFG, $OUTPUT;
    require_once($CFG->libdir . '/gradelib.php');

    if (property_exists($crucible, 'cmidnumber')) { // May not be always present.
        $params = array('itemname' => $crucible->name, 'idnumber' => $crucible->cmidnumber);
    } else {
        $params = array('itemname' => $crucible->name);
    }
    if ($crucible->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $crucible->grade;
        $params['grademin']  = 0;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }
    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }
    return grade_update('mod/crucible', $crucible->course, 'mod', 'crucible', $crucible->id, 0, $grades, $params);
}


/**
 * Delete grade item for given lab
 *
 * @category grade
 * @param object $crucible object
 * @return object crucible
 */
function crucible_grade_item_delete($crucible) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/crucible', $crucible->course, 'mod', 'crucible', $crucible->id, 0,
            null, array('deleted' => 1));
}

/**
 * Return grade for given user or all users.
 *
 * @param int $crucible id of crucible
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none. These are raw grades.
 */
function crucible_get_user_grades($crucible, $userid = 0) {
    global $CFG, $DB;

    $params = array($crucible->id);
    $usertest = '';
    if ($userid) {
        $params[] = $userid;
        $usertest = 'AND u.id = ?';
    }
    return $DB->get_records_sql("
            SELECT
                u.id,
                u.id AS userid,
                cg.grade AS rawgrade,
                cg.timemodified AS dategraded,
                MAX(ca.timefinish) AS datesubmitted

            FROM {user} u
            JOIN {crucible_grades} cg ON u.id = cg.userid
            JOIN {crucible_attempts} ca ON ca.crucibleid = cg.crucibleid AND ca.userid = u.id

            WHERE cg.crucibleid = ?
            $usertest
            GROUP BY u.id, cg.grade, cg.timemodified", $params);
}

function crucible_extend_settings_navigation($settingsnav, $context) {
    global $PAGE;

    $keys = $context->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false and array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    $url = new moodle_url('/mod/crucible/review.php', array('id' => $PAGE->cm->id));
    $node = navigation_node::create(get_string('reviewtext', 'mod_crucible'),
            new moodle_url($url),
            navigation_node::TYPE_SETTING, null, 'mod_crucible_review', new pix_icon('i/grades', 'grades'));
    $context->add_node($node, $beforekey);

    if (has_capability('mod/crucible:manage', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/crucible/manage.php', array('c' => $PAGE->cm->course));
        $node = navigation_node::create(get_string('managetext', 'mod_crucible'),
                new moodle_url($url),
                navigation_node::TYPE_SETTING, null, 'mod_crucible_manage', new pix_icon('i/grades', 'grades'));
        $context->add_node($node, $beforekey);
    }

    if (has_capability('mod/crucible:manage', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/crucible/tasks.php', array('id' => $PAGE->cm->id));
        $node = navigation_node::create(get_string('managetasks', 'mod_crucible'),
                new moodle_url($url),
                navigation_node::TYPE_SETTING, null, 'mod_crucible_tasks', new pix_icon('i/completion-manual-enabled', 'tasks'));
        $context->add_node($node, $beforekey);
    }


}
