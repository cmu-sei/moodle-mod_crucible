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
 * crucible module upgrade code
 *
 * This file keeps track of upgrades to
 * the resource module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
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

defined('MOODLE_INTERNAL') || die;

function xmldb_crucible_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2020011500) {

        // Define table crucible_grades to be created.
        $table = new xmldb_table('crucible_grades');

        // Adding fields to table crucible_grades.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('crucibleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('grade', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, '0.00000');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table crucible_grades.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for crucible_grades.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table crucible_attempts to be created.
        $table = new xmldb_table('crucible_attempts');

        // Adding fields to table crucible_attempts.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('crucibleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('attemptnum', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timestart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timefinish', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table crucible_attempts.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for crucible_attempts.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Crucible savepoint reached.
        upgrade_mod_savepoint(true, 2020011500, 'crucible');
    }


    if ($oldversion < 2020040300) {

        // Define field clock to be added to crucible.
        $table = new xmldb_table('crucible');
        $field = new xmldb_field('clock', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'vmapp');

        // Conditionally launch add field clock.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field tasks to be added to crucible_attempts.
        $table = new xmldb_table('crucible_attempts');
        $field = new xmldb_field('tasks', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'attemptnum');

        // Conditionally launch add field tasks.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field score to be added to crucible_attempts.
        $table = new xmldb_table('crucible_attempts');
        $field = new xmldb_field('score', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'tasks');

        // Conditionally launch add field score.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Crucible savepoint reached.
        upgrade_mod_savepoint(true, 2020040300, 'crucible');
    }

    if ($oldversion < 2020040800) {

        // Define field sessionid to be added to crucible_attempts.
        $table = new xmldb_table('crucible_attempts');
        $field = new xmldb_field('sessionid', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'crucibleid');

        // Conditionally launch add field sessionid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field eventid to be added to crucible_attempts.
        $table = new xmldb_table('crucible_attempts');
        $field = new xmldb_field('eventid', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'sessionid');

        // Conditionally launch add field eventid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field state to be added to crucible_attempts.
        $table = new xmldb_table('crucible_attempts');
        $field = new xmldb_field('state', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'inprogress', 'attemptnum');

        // Conditionally launch add field state.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Changing type of field tasks on table crucible_attempts to text.
        $table = new xmldb_table('crucible_attempts');
        $field = new xmldb_field('tasks', XMLDB_TYPE_TEXT, null, null, null, null, null, 'state');

        // Launch change of type for field tasks.
        $dbman->change_field_type($table, $field);

        // Rename field definition on table crucible to eventtemplate.
        $table = new xmldb_table('crucible');
        $field = new xmldb_field('definition', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'intro');

        // Launch rename field eventtemplate.
        $dbman->rename_field($table, $field, 'eventtemplate');

        // Crucible savepoint reached.
        upgrade_mod_savepoint(true, 2020040800, 'crucible');
    }

    if ($oldversion < 2020040805) {

        // Changing nullability of field sessionid on table crucible_attempts to not null.
        $table = new xmldb_table('crucible_attempts');
        $field = new xmldb_field('sessionid', XMLDB_TYPE_TEXT, null, null, null, null, null, 'crucibleid');

        // Launch change of nullability for field sessionid.
        $dbman->change_field_notnull($table, $field);

        // Crucible savepoint reached.
        upgrade_mod_savepoint(true, 2020040805, 'crucible');
    }

    if ($oldversion < 2020040900) {

        // Changing nullability of field eventid on table crucible_attempts to not null.
        $table = new xmldb_table('crucible_attempts');
        $field = new xmldb_field('eventid', XMLDB_TYPE_TEXT, null, null, null, null, null, 'sessionid');

        // Launch change of nullability for field eventid.
        $dbman->change_field_notnull($table, $field);

        // Define field attemptnum to be dropped from crucible_attempts.
        $table = new xmldb_table('crucible_attempts');
        $field = new xmldb_field('attemptnum');

        // Conditionally launch drop field attemptnum.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Crucible savepoint reached.
        upgrade_mod_savepoint(true, 2020040900, 'crucible');
    }

    if ($oldversion < 2020040903) {

        // Define field timeopen to be added to crucible.
        $table = new xmldb_table('crucible');
        $field = new xmldb_field('timeopen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'clock');

        // Conditionally launch add field timeopen.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field timeclose to be added to crucible.
        $table = new xmldb_table('crucible');
        $field = new xmldb_field('timeclose', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timeopen');

        // Conditionally launch add field timeclose.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field grade to be added to crucible.
        $table = new xmldb_table('crucible');
        $field = new xmldb_field('grade', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, '0', 'timeopen');

        // Conditionally launch add field grade.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field grademethod to be added to crucible.
        $table = new xmldb_table('crucible');
        $field = new xmldb_field('grademethod', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'grade');

        // Conditionally launch add field grademethod.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Crucible savepoint reached.
        upgrade_mod_savepoint(true, 2020040903, 'crucible');
    }

    if ($oldversion < 2020040904) {

        // Define field introformat to be added to crucible.
        $table = new xmldb_table('crucible');
        $field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'intro');

        // Conditionally launch add field introformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Crucible savepoint reached.
        upgrade_mod_savepoint(true, 2020040904, 'crucible');
    }

    if ($oldversion < 2020041000) {

        // Changing type of field grade on table crucible to int.
        $table = new xmldb_table('crucible');
        $field = new xmldb_field('grade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timeclose');

        // Launch change of type for field grade.
        $dbman->change_field_type($table, $field);

        // Crucible savepoint reached.
        upgrade_mod_savepoint(true, 2020041000, 'crucible');
    }

    if ($oldversion < 2020041300) {

        // Rename field eventtemplate on table crucible to eventtemplateid.
        $table = new xmldb_table('crucible');
        $field = new xmldb_field('eventtemplate', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'introformat');

        // Launch rename field eventtemplateid.
        $dbman->rename_field($table, $field, 'eventtemplateid');

        // Crucible savepoint reached.
        upgrade_mod_savepoint(true, 2020041300, 'crucible');
    }

    if ($oldversion < 2020041307) {

        // Define field endtime to be added to crucible_attempts.
        $table = new xmldb_table('crucible_attempts');
        $field = new xmldb_field('endtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 1586788681, 'score');

        // Conditionally launch add field endtime.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Crucible savepoint reached.
        upgrade_mod_savepoint(true, 2020041307, 'crucible');
    }

    if ($oldversion < 2020050602) {

        // Define field extendevent to be added to crucible.
        $table = new xmldb_table('crucible');
        $field = new xmldb_field('extendevent', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'clock');

        // Conditionally launch add field extendevent.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Crucible savepoint reached.
        upgrade_mod_savepoint(true, 2020050602, 'crucible');
    }

    if ($oldversion < 2020051801) {

        // Define table crucible_tasks to be created.
        $table = new xmldb_table('crucible_tasks');

        // Adding fields to table crucible_tasks.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('crucibleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('scenarioid', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('dispatchtaskid', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('name', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('gradable', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('visible', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('points', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('multiple', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table crucible_tasks.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for crucible_tasks.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Crucible savepoint reached.
        upgrade_mod_savepoint(true, 2020051801, 'crucible');
    }


    if ($oldversion < 2020051802) {

        // Define table crucible_task_results to be created.
        $table = new xmldb_table('crucible_task_results');

        // Adding fields to table crucible_task_results.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('taskid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('vmname', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('score', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('comment', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table crucible_task_results.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for crucible_task_results.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Crucible savepoint reached.
        upgrade_mod_savepoint(true, 2020051802, 'crucible');
    }

    if ($oldversion < 2020052000) {

        // Rename field sessionid on table crucible_attempts to scenarioid.
        $table = new xmldb_table('crucible_attempts');
        $field = new xmldb_field('sessionid', XMLDB_TYPE_TEXT, null, null, null, null, null, 'crucibleid');

        // Launch rename field sessionid.
        $dbman->rename_field($table, $field, 'scenarioid');

        // Crucible savepoint reached.
        upgrade_mod_savepoint(true, 2020052000, 'crucible');
    }

    if ($oldversion < 2020052001) {

        // Rename field scenarioid on table crucible_tasks to scenariotemplateid.
        $table = new xmldb_table('crucible_tasks');
        $field = new xmldb_field('scenarioid', XMLDB_TYPE_TEXT, null, null, null, null, null, 'crucibleid');

        // Launch rename field scenariotemplateid.
        $dbman->rename_field($table, $field, 'scenariotemplateid');

        // Crucible savepoint reached.
        upgrade_mod_savepoint(true, 2020052001, 'crucible');
    }


    return true;
}

