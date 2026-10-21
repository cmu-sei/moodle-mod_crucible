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

/**
 * Privacy Subsystem implementation for mod_crucible.
 *
 * @package    mod_crucible
 * @copyright  Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_crucible\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for mod_crucible.
 *
 * The activity stores a userid in crucible_attempts, crucible_attempt_users, crucible_grades and
 * crucible_bulkdeploy_user, and the instructor's userid on each bulk-deploy job, so this is a
 * full provider rather than a null_provider.
 *
 * @copyright  Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data this plugin stores and sends.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('crucible_attempts', [
            'userid' => 'privacy:metadata:crucible_attempts:userid',
            'eventid' => 'privacy:metadata:crucible_attempts:eventid',
            'scenarioid' => 'privacy:metadata:crucible_attempts:scenarioid',
            'state' => 'privacy:metadata:crucible_attempts:state',
            'tasks' => 'privacy:metadata:crucible_attempts:tasks',
            'score' => 'privacy:metadata:crucible_attempts:score',
            'endtime' => 'privacy:metadata:crucible_attempts:endtime',
            'timestart' => 'privacy:metadata:crucible_attempts:timestart',
            'timefinish' => 'privacy:metadata:crucible_attempts:timefinish',
            'timemodified' => 'privacy:metadata:crucible_attempts:timemodified',
        ], 'privacy:metadata:crucible_attempts');

        $collection->add_database_table('crucible_attempt_users', [
            'attemptid' => 'privacy:metadata:crucible_attempt_users:attemptid',
            'userid' => 'privacy:metadata:crucible_attempt_users:userid',
        ], 'privacy:metadata:crucible_attempt_users');

        $collection->add_database_table('crucible_task_results', [
            'attemptid' => 'privacy:metadata:crucible_task_results:attemptid',
            'vmname' => 'privacy:metadata:crucible_task_results:vmname',
            'status' => 'privacy:metadata:crucible_task_results:status',
            'score' => 'privacy:metadata:crucible_task_results:score',
            'comment' => 'privacy:metadata:crucible_task_results:comment',
            'timemodified' => 'privacy:metadata:crucible_task_results:timemodified',
        ], 'privacy:metadata:crucible_task_results');

        $collection->add_database_table('crucible_grades', [
            'userid' => 'privacy:metadata:crucible_grades:userid',
            'grade' => 'privacy:metadata:crucible_grades:grade',
            'timemodified' => 'privacy:metadata:crucible_grades:timemodified',
        ], 'privacy:metadata:crucible_grades');

        $collection->add_database_table('crucible_bulkdeploy_user', [
            'userid' => 'privacy:metadata:crucible_bulkdeploy_user:userid',
            'eventid' => 'privacy:metadata:crucible_bulkdeploy_user:eventid',
            'status' => 'privacy:metadata:crucible_bulkdeploy_user:status',
            'errormessage' => 'privacy:metadata:crucible_bulkdeploy_user:errormessage',
            'timestarted' => 'privacy:metadata:crucible_bulkdeploy_user:timestarted',
            'timecompleted' => 'privacy:metadata:crucible_bulkdeploy_user:timecompleted',
        ], 'privacy:metadata:crucible_bulkdeploy_user');

        $collection->add_database_table('crucible_bulkdeploy_job', [
            'initiatorid' => 'privacy:metadata:crucible_bulkdeploy_job:initiatorid',
            'cancelledby' => 'privacy:metadata:crucible_bulkdeploy_job:cancelledby',
            'rolefilter' => 'privacy:metadata:crucible_bulkdeploy_job:rolefilter',
            'status' => 'privacy:metadata:crucible_bulkdeploy_job:status',
            'timecreated' => 'privacy:metadata:crucible_bulkdeploy_job:timecreated',
        ], 'privacy:metadata:crucible_bulkdeploy_job');

        // Bulk deployment sends start_event() the user's Alloy GUID and display name, and
        // enlist_user_in_event_as_admin() sends the same pair. Alloy then provisions the lab
        // through Caster, Player and Steamfitter on the user's behalf.
        $collection->add_external_location_link('alloy', [
            'userid' => 'privacy:metadata:alloy:userid',
            'username' => 'privacy:metadata:alloy:username',
        ], 'privacy:metadata:alloy');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * A user has data in a Crucible activity if they own an attempt, joined someone else's attempt,
     * have a grade or a bulk-deploy row, or started or cancelled a bulk-deploy job.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $frommodule = "FROM {context} c
                       JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                       JOIN {modules} m ON m.id = cm.module AND m.name = 'crucible'
                       JOIN {crucible} cr ON cr.id = cm.instance";

        // Attempts the user owns.
        $sql = "SELECT c.id
                  $frommodule
                  JOIN {crucible_attempts} ca ON ca.crucibleid = cr.id
                 WHERE ca.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);

        // Attempts the user joined by invitation.
        $sql = "SELECT c.id
                  $frommodule
                  JOIN {crucible_attempts} ca ON ca.crucibleid = cr.id
                  JOIN {crucible_attempt_users} cau ON cau.attemptid = ca.id
                 WHERE cau.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);

        $sql = "SELECT c.id
                  $frommodule
                  JOIN {crucible_grades} cg ON cg.crucibleid = cr.id
                 WHERE cg.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);

        // Bulk-deploy: the user the lab was deployed for.
        $sql = "SELECT c.id
                  $frommodule
                  JOIN {crucible_bulkdeploy_job} bj ON bj.crucibleid = cr.id
                  JOIN {crucible_bulkdeploy_user} bu ON bu.jobid = bj.id
                 WHERE bu.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);

        // Bulk-deploy: the user who started or cancelled the job.
        $sql = "SELECT c.id
                  $frommodule
                  JOIN {crucible_bulkdeploy_job} bj ON bj.crucibleid = cr.id
                 WHERE bj.initiatorid = :initiatorid OR bj.cancelledby = :cancelledby";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'initiatorid' => $userid,
            'cancelledby' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this
     *                           context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $params = ['cmid' => $context->instanceid, 'modname' => 'crucible'];

        $frommodule = "FROM {course_modules} cm
                       JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                       JOIN {crucible} cr ON cr.id = cm.instance";

        $sql = "SELECT ca.userid
                  $frommodule
                  JOIN {crucible_attempts} ca ON ca.crucibleid = cr.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT cau.userid
                  $frommodule
                  JOIN {crucible_attempts} ca ON ca.crucibleid = cr.id
                  JOIN {crucible_attempt_users} cau ON cau.attemptid = ca.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT cg.userid
                  $frommodule
                  JOIN {crucible_grades} cg ON cg.crucibleid = cr.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT bu.userid
                  $frommodule
                  JOIN {crucible_bulkdeploy_job} bj ON bj.crucibleid = cr.id
                  JOIN {crucible_bulkdeploy_user} bu ON bu.jobid = bj.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT bj.initiatorid
                  $frommodule
                  JOIN {crucible_bulkdeploy_job} bj ON bj.crucibleid = cr.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('initiatorid', $sql, $params);

        $sql = "SELECT bj.cancelledby
                  $frommodule
                  JOIN {crucible_bulkdeploy_job} bj ON bj.crucibleid = cr.id
                 WHERE cm.id = :cmid AND bj.cancelledby IS NOT NULL";
        $userlist->add_from_sql('cancelledby', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = (int)$user->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('crucible', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $crucible = $DB->get_record('crucible', ['id' => $cm->instance]);
            if (!$crucible) {
                continue;
            }

            // The module's own settings, name and intro, which core expects every activity to write.
            $contextdata = helper::get_context_data($context, $user);
            helper::export_context_files($context, $user);
            writer::with_context($context)->export_data([], $contextdata);

            self::export_attempts($userid, $context, $crucible);
            self::export_joined_attempts($userid, $context, $crucible);
            self::export_grade($userid, $context, $crucible);
            self::export_bulkdeploy($userid, $context, $crucible);
        }
    }

    /**
     * Export the attempts the user owns in one activity, with the task results for each.
     *
     * @param int $userid The user being exported.
     * @param \context_module $context The activity context.
     * @param \stdClass $crucible The crucible activity record.
     */
    protected static function export_attempts(int $userid, \context_module $context, \stdClass $crucible) {
        global $DB;

        $attempts = $DB->get_records(
            'crucible_attempts',
            ['crucibleid' => $crucible->id, 'userid' => $userid],
            'id ASC'
        );

        foreach ($attempts as $attempt) {
            $subcontext = [get_string('privacy:path:attempts', 'mod_crucible'), $attempt->id];

            $data = (object)[
                'state' => $attempt->state,
                'eventid' => $attempt->eventid,
                'scenarioid' => $attempt->scenarioid,
                'tasks' => $attempt->tasks,
                'score' => $attempt->score,
                'timestart' => transform::datetime($attempt->timestart),
                'timemodified' => transform::datetime($attempt->timemodified),
            ];
            if (!empty($attempt->endtime)) {
                $data->endtime = transform::datetime($attempt->endtime);
            }
            if (!empty($attempt->timefinish)) {
                $data->timefinish = transform::datetime($attempt->timefinish);
            }
            writer::with_context($context)->export_data($subcontext, $data);

            // The task results belong to the attempt, including any comment a teacher left on
            // them; the comment does not record which teacher wrote it.
            $sql = "SELECT tr.id, tr.vmname, tr.status, tr.score, tr.comment, tr.timemodified, t.name AS taskname
                      FROM {crucible_task_results} tr
                 LEFT JOIN {crucible_tasks} t ON t.id = tr.taskid
                     WHERE tr.attemptid = :attemptid
                  ORDER BY tr.id ASC";
            $results = $DB->get_records_sql($sql, ['attemptid' => $attempt->id]);
            foreach ($results as $result) {
                $resultdata = (object)[
                    'task' => $result->taskname,
                    'vmname' => $result->vmname,
                    'status' => $result->status,
                    'score' => $result->score,
                    'comment' => $result->comment,
                ];
                if (!empty($result->timemodified)) {
                    $resultdata->timemodified = transform::datetime($result->timemodified);
                }
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('privacy:path:taskresults', 'mod_crucible'), $result->id]),
                    $resultdata
                );
            }
        }
    }

    /**
     * Export the user's membership of attempts that other users own in one activity.
     *
     * Only the membership and the shared lab it gave access to are exported; the owner's score,
     * task results and identity are the owner's data.
     *
     * @param int $userid The user being exported.
     * @param \context_module $context The activity context.
     * @param \stdClass $crucible The crucible activity record.
     */
    protected static function export_joined_attempts(int $userid, \context_module $context, \stdClass $crucible) {
        global $DB;

        $sql = "SELECT cau.id, ca.id AS attemptid, ca.eventid, ca.state, ca.timestart, ca.timefinish
                  FROM {crucible_attempt_users} cau
                  JOIN {crucible_attempts} ca ON ca.id = cau.attemptid
                 WHERE ca.crucibleid = :crucibleid AND cau.userid = :userid AND ca.userid <> :ownerid
              ORDER BY cau.id ASC";
        $rows = $DB->get_records_sql($sql, [
            'crucibleid' => $crucible->id,
            'userid' => $userid,
            'ownerid' => $userid,
        ]);

        foreach ($rows as $row) {
            $data = (object)[
                'attemptid' => $row->attemptid,
                'eventid' => $row->eventid,
                'state' => $row->state,
                'timestart' => transform::datetime($row->timestart),
            ];
            if (!empty($row->timefinish)) {
                $data->timefinish = transform::datetime($row->timefinish);
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:path:joinedattempts', 'mod_crucible'), $row->attemptid],
                $data
            );
        }
    }

    /**
     * Export the user's stored grade for one activity.
     *
     * @param int $userid The user being exported.
     * @param \context_module $context The activity context.
     * @param \stdClass $crucible The crucible activity record.
     */
    protected static function export_grade(int $userid, \context_module $context, \stdClass $crucible) {
        global $DB;

        $grades = $DB->get_records('crucible_grades', ['crucibleid' => $crucible->id, 'userid' => $userid]);
        foreach ($grades as $grade) {
            writer::with_context($context)->export_data(
                [get_string('privacy:path:grades', 'mod_crucible'), $grade->id],
                (object)[
                    'grade' => $grade->grade,
                    'timemodified' => transform::datetime($grade->timemodified),
                ]
            );
        }
    }

    /**
     * Export the user's bulk-deploy involvement for one activity, as a subject and as an initiator.
     *
     * @param int $userid The user being exported.
     * @param \context_module $context The activity context.
     * @param \stdClass $crucible The crucible activity record.
     */
    protected static function export_bulkdeploy(int $userid, \context_module $context, \stdClass $crucible) {
        global $DB;

        $jobs = $DB->get_records('crucible_bulkdeploy_job', ['crucibleid' => $crucible->id], 'id ASC');
        foreach ($jobs as $job) {
            $subcontext = [get_string('privacy:path:bulkdeploy', 'mod_crucible'), $job->id];

            if ((int)$job->initiatorid === $userid || (int)$job->cancelledby === $userid) {
                writer::with_context($context)->export_data($subcontext, (object)[
                    'role' => (int)$job->initiatorid === $userid ? 'initiator' : 'cancelledby',
                    'status' => $job->status,
                    'rolefilter' => $job->rolefilter,
                    'totalusers' => $job->totalusers,
                    'timecreated' => transform::datetime($job->timecreated),
                ]);
            }

            $rows = $DB->get_records('crucible_bulkdeploy_user', ['jobid' => $job->id, 'userid' => $userid]);
            foreach ($rows as $row) {
                $data = (object)[
                    'eventid' => $row->eventid,
                    'status' => $row->status,
                    'errormessage' => $row->errormessage,
                ];
                if (!empty($row->timestarted)) {
                    $data->timestarted = transform::datetime($row->timestarted);
                }
                if (!empty($row->timecompleted)) {
                    $data->timecompleted = transform::datetime($row->timecompleted);
                }
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('privacy:path:deployment', 'mod_crucible')]),
                    $data
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * Ends the Alloy events of unfinished attempts and live bulk deployments best-effort, as
     * deleting the activity does, then removes every row.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $CFG, $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('crucible', $context->instanceid);
        if (!$cm) {
            return;
        }
        $crucible = $DB->get_record('crucible', ['id' => $cm->instance]);
        if (!$crucible) {
            return;
        }

        // The shared crucible_delete_all_attempts() is what crucible_delete_instance() uses too,
        // which keeps one definition of "wipe this activity" rather than two that can drift apart.
        require_once($CFG->dirroot . '/mod/crucible/lib.php');
        crucible_delete_all_attempts($crucible);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int)$contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('crucible', $context->instanceid);
            if (!$cm) {
                continue;
            }
            self::delete_users_in_activity((int)$cm->instance, [$userid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('crucible', $context->instanceid);
        if (!$cm) {
            return;
        }
        self::delete_users_in_activity((int)$cm->instance, $userlist->get_userids());
    }

    /**
     * Remove the given users' attempts, memberships, grades and bulk-deploy rows from one activity.
     *
     * @param int $crucibleid The crucible activity id.
     * @param array $userids The users to remove.
     */
    protected static function delete_users_in_activity(int $crucibleid, array $userids) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/crucible/lib.php');

        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
        $params = array_merge(['crucibleid' => $crucibleid], $inparams);

        // End the users' running Alloy events first, best-effort: once their rows are gone nothing
        // in Moodle points at those events any more. An invited user's membership is removed below,
        // but the event they joined belongs to its owner and keeps running.
        crucible_end_alloy_events(crucible_live_event_ids($crucibleid, $userids));

        // Attempts the users own go entirely, with their task results and the memberships of anyone
        // they invited, since those memberships point at an attempt that no longer exists.
        $ownedids = $DB->get_fieldset_select(
            'crucible_attempts',
            'id',
            "crucibleid = :crucibleid AND userid $insql",
            $params
        );
        crucible_delete_attempts($ownedids);

        // A user who only joined someone else's attempt loses their membership; the owner's attempt stays.
        $allids = $DB->get_fieldset_select('crucible_attempts', 'id', 'crucibleid = :crucibleid', [
            'crucibleid' => $crucibleid,
        ]);
        if ($allids) {
            [$attemptsql, $attemptparams] = $DB->get_in_or_equal($allids, SQL_PARAMS_NAMED, 'att');
            $DB->delete_records_select(
                'crucible_attempt_users',
                "attemptid $attemptsql AND userid $insql",
                array_merge($attemptparams, $inparams)
            );
        }

        $DB->delete_records_select('crucible_grades', "crucibleid = :crucibleid AND userid $insql", $params);

        // Bulk-deploy rows for these users, in this activity's jobs. The job rows themselves are
        // shared state describing the activity rather than one person's data, so they stay: only
        // the initiatorid and cancelledby references are scrubbed.
        $jobids = $DB->get_fieldset_select(
            'crucible_bulkdeploy_job',
            'id',
            'crucibleid = :crucibleid',
            ['crucibleid' => $crucibleid]
        );
        if ($jobids) {
            [$jobsql, $jobparams] = $DB->get_in_or_equal($jobids, SQL_PARAMS_NAMED, 'job');
            $DB->delete_records_select(
                'crucible_bulkdeploy_user',
                "jobid $jobsql AND userid $insql",
                array_merge($jobparams, $inparams)
            );

            // The initiatorid column is NOT NULL, so it cannot be blanked; point it at id 0 the way
            // core does for unattributable rows. cancelledby is nullable and can simply be cleared.
            $DB->set_field_select(
                'crucible_bulkdeploy_job',
                'initiatorid',
                0,
                "id $jobsql AND initiatorid $insql",
                array_merge($jobparams, $inparams)
            );
            $DB->set_field_select(
                'crucible_bulkdeploy_job',
                'cancelledby',
                null,
                "id $jobsql AND cancelledby $insql",
                array_merge($jobparams, $inparams)
            );
        }
    }
}
