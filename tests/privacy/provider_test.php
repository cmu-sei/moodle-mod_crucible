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

namespace mod_crucible\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Unit tests for the mod_crucible privacy provider.
 *
 * @package    mod_crucible
 * @category   test
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass The course holding both activities. */
    protected $course;

    /** @var \stdClass First Crucible activity. */
    protected $crucible1;

    /** @var \stdClass Second Crucible activity, used to prove contexts are reported separately. */
    protected $crucible2;

    /** @var \stdClass The user whose data is being requested. */
    protected $student1;

    /** @var \stdClass A second user, whose data must survive every deletion aimed at the first. */
    protected $student2;

    /** @var \stdClass An instructor who runs bulk deployments. */
    protected $teacher;

    /**
     * Build the course, activities and users shared by every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->crucible1 = $generator->create_module('crucible', ['course' => $this->course->id]);
        $this->crucible2 = $generator->create_module('crucible', ['course' => $this->course->id]);
        $this->student1 = $generator->create_user();
        $this->student2 = $generator->create_user();
        $this->teacher = $generator->create_user();
    }

    /**
     * The module context for a Crucible activity.
     *
     * @param \stdClass $crucible The activity.
     * @return \context_module
     */
    protected function context_for(\stdClass $crucible): \context_module {
        $cm = get_coursemodule_from_instance('crucible', $crucible->id, $this->course->id, false, MUST_EXIST);
        return \context_module::instance($cm->id);
    }

    /**
     * Create an attempt with one task result.
     *
     * @param \stdClass $crucible The activity.
     * @param \stdClass $user The attempt owner.
     * @return int The attempt id.
     */
    protected function create_attempt(\stdClass $crucible, \stdClass $user): int {
        global $DB;

        $now = time();
        $attemptid = $DB->insert_record('crucible_attempts', (object)[
            'crucibleid' => $crucible->id,
            'userid' => $user->id,
            'eventid' => 'event-' . $user->id,
            'scenarioid' => 'scenario-' . $user->id,
            'state' => 'finished',
            'score' => 75,
            'endtime' => $now + 3600,
            'timestart' => $now,
            'timefinish' => $now + 60,
            'timemodified' => $now,
        ]);
        $taskid = $DB->insert_record('crucible_tasks', (object)[
            'crucibleid' => $crucible->id,
            'name' => 'Check service',
        ]);
        $DB->insert_record('crucible_task_results', (object)[
            'taskid' => $taskid,
            'attemptid' => $attemptid,
            'vmname' => 'vm-' . $user->id,
            'status' => 'succeeded',
            'score' => 10,
            'comment' => 'Well done ' . $user->id,
            'timemodified' => $now,
        ]);
        return $attemptid;
    }

    /**
     * Give a user an attempt, a grade and a bulk-deploy row in an activity.
     *
     * @param \stdClass $crucible The activity.
     * @param \stdClass $user The user.
     * @return int The attempt id.
     */
    protected function create_user_data(\stdClass $crucible, \stdClass $user): int {
        global $DB;

        $attemptid = $this->create_attempt($crucible, $user);
        $DB->insert_record('crucible_grades', (object)[
            'crucibleid' => $crucible->id,
            'userid' => $user->id,
            'grade' => 75,
            'timemodified' => time(),
        ]);
        $DB->insert_record('crucible_bulkdeploy_user', (object)[
            'jobid' => $this->get_job($crucible),
            'userid' => $user->id,
            'eventid' => 'event-' . $user->id,
            'status' => 'ready',
            'timestarted' => time(),
        ]);
        return $attemptid;
    }

    /**
     * Get (creating on first use) the teacher's bulk-deploy job for an activity.
     *
     * @param \stdClass $crucible The activity.
     * @return int The job id.
     */
    protected function get_job(\stdClass $crucible): int {
        global $DB;

        $jobid = $DB->get_field('crucible_bulkdeploy_job', 'id', ['crucibleid' => $crucible->id]);
        if ($jobid) {
            return (int)$jobid;
        }
        return $DB->insert_record('crucible_bulkdeploy_job', (object)[
            'crucibleid' => $crucible->id,
            'courseid' => $this->course->id,
            'initiatorid' => $this->teacher->id,
            'cancelledby' => $this->teacher->id,
            'totalusers' => 2,
            'status' => 'cancelled',
            'timecreated' => time(),
        ]);
    }

    /**
     * Invite a user into another user's attempt.
     *
     * @param int $attemptid The attempt.
     * @param \stdClass $user The invited user.
     */
    protected function join_attempt(int $attemptid, \stdClass $user): void {
        global $DB;
        $DB->insert_record('crucible_attempt_users', (object)['attemptid' => $attemptid, 'userid' => $user->id]);
    }

    /**
     * Every table and the Alloy external location are declared.
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('mod_crucible'));
        $names = array_map(fn($item) => $item->get_name(), $collection->get_collection());

        $expectednames = [
            'crucible_attempts',
            'crucible_attempt_users',
            'crucible_task_results',
            'crucible_grades',
            'crucible_bulkdeploy_user',
            'crucible_bulkdeploy_job',
            'alloy',
        ];
        foreach ($expectednames as $expected) {
            $this->assertContains($expected, $names);
        }
    }

    /**
     * Owners, invitees, grades, deploy subjects and job initiators all resolve to the right context.
     */
    public function test_get_contexts_for_userid(): void {
        $this->assertEmpty(provider::get_contexts_for_userid($this->student1->id)->get_contextids());

        $attemptid = $this->create_user_data($this->crucible1, $this->student1);
        $this->join_attempt($attemptid, $this->student2);

        $context1 = $this->context_for($this->crucible1);
        $this->assertEquals([$context1->id], provider::get_contexts_for_userid($this->student1->id)->get_contextids());
        $this->assertEquals([$context1->id], provider::get_contexts_for_userid($this->student2->id)->get_contextids());
        $this->assertEquals([$context1->id], provider::get_contexts_for_userid($this->teacher->id)->get_contextids());
    }

    /**
     * Every kind of user reference in the activity is reported.
     */
    public function test_get_users_in_context(): void {
        $attemptid = $this->create_user_data($this->crucible1, $this->student1);
        $this->join_attempt($attemptid, $this->student2);
        $this->create_user_data($this->crucible2, $this->student2);

        $userlist = new userlist($this->context_for($this->crucible1), 'mod_crucible');
        provider::get_users_in_context($userlist);
        $expected = [$this->student1->id, $this->student2->id, $this->teacher->id];
        $actual = $userlist->get_userids();
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual);
    }

    /**
     * The owner gets the attempt and its task results; an invitee only their membership.
     */
    public function test_export_user_data(): void {
        global $DB;

        $attemptid = $this->create_user_data($this->crucible1, $this->student1);
        $this->join_attempt($attemptid, $this->student2);
        $context = $this->context_for($this->crucible1);

        $this->export_context_data_for_user($this->student1->id, $context, 'mod_crucible');
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $attempts = get_string('privacy:path:attempts', 'mod_crucible');
        $attempt = $writer->get_data([$attempts, $attemptid]);
        $this->assertEquals('event-' . $this->student1->id, $attempt->eventid);
        $this->assertEquals(75, $attempt->score);
        $gradeid = $DB->get_field('crucible_grades', 'id', ['userid' => $this->student1->id]);
        $grade = $writer->get_data([get_string('privacy:path:grades', 'mod_crucible'), $gradeid]);
        $this->assertEquals(75, $grade->grade);
        $this->assertEmpty($writer->get_data([get_string('privacy:path:joinedattempts', 'mod_crucible'), $attemptid]));

        \core_privacy\local\request\writer::reset();
        $this->export_context_data_for_user($this->student2->id, $context, 'mod_crucible');
        $writer = writer::with_context($context);
        $this->assertEmpty($writer->get_data([$attempts, $attemptid]));
        $joined = $writer->get_data([get_string('privacy:path:joinedattempts', 'mod_crucible'), $attemptid]);
        $this->assertEquals($attemptid, $joined->attemptid);
        $this->assertObjectNotHasProperty('score', $joined);

        \core_privacy\local\request\writer::reset();
        $this->export_context_data_for_user($this->teacher->id, $context, 'mod_crucible');
        $writer = writer::with_context($context);
        $jobid = $this->get_job($this->crucible1);
        $job = $writer->get_data([get_string('privacy:path:bulkdeploy', 'mod_crucible'), $jobid]);
        $this->assertEquals('initiator', $job->role);
    }

    /**
     * The task result comment is exported under the owner's attempt.
     */
    public function test_export_user_data_task_results(): void {
        global $DB;

        $attemptid = $this->create_user_data($this->crucible1, $this->student1);
        $context = $this->context_for($this->crucible1);
        $resultid = $DB->get_field('crucible_task_results', 'id', ['attemptid' => $attemptid]);

        $this->export_context_data_for_user($this->student1->id, $context, 'mod_crucible');
        $result = writer::with_context($context)->get_data([
            get_string('privacy:path:attempts', 'mod_crucible'),
            $attemptid,
            get_string('privacy:path:taskresults', 'mod_crucible'),
            $resultid,
        ]);
        $this->assertEquals('Check service', $result->task);
        $this->assertEquals('Well done ' . $this->student1->id, $result->comment);
    }

    /**
     * Deleting a context wipes every row for that activity and nothing in the other one.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $attemptid = $this->create_user_data($this->crucible1, $this->student1);
        $this->join_attempt($attemptid, $this->student2);
        $this->create_user_data($this->crucible2, $this->student2);

        // The bulk deployment left a ready event; with no OAuth issuer configured ending it fails,
        // which is logged twice (setup_system() and the helper) without stopping the wipe.
        provider::delete_data_for_all_users_in_context($this->context_for($this->crucible1));
        $this->assertDebuggingCalledCount(2);

        $this->assertFalse($DB->record_exists('crucible_attempts', ['crucibleid' => $this->crucible1->id]));
        $this->assertFalse($DB->record_exists('crucible_task_results', ['attemptid' => $attemptid]));
        $this->assertFalse($DB->record_exists('crucible_attempt_users', ['attemptid' => $attemptid]));
        $this->assertFalse($DB->record_exists('crucible_grades', ['crucibleid' => $this->crucible1->id]));
        $this->assertFalse($DB->record_exists('crucible_bulkdeploy_job', ['crucibleid' => $this->crucible1->id]));

        $this->assertEquals(1, $DB->count_records('crucible_attempts', ['crucibleid' => $this->crucible2->id]));
        $this->assertEquals(1, $DB->count_records('crucible_grades', ['crucibleid' => $this->crucible2->id]));
        $this->assertEquals(1, $DB->count_records('crucible_bulkdeploy_user', ['userid' => $this->student2->id]));
    }

    /**
     * Deleting the owner removes their attempt; deleting an invitee removes only the membership.
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $attempt1 = $this->create_user_data($this->crucible1, $this->student1);
        $attempt2 = $this->create_user_data($this->crucible1, $this->student2);
        $this->join_attempt($attempt2, $this->student1);
        $context = $this->context_for($this->crucible1);

        // Student1's ready deployment is live; with no OAuth issuer ending it is logged twice
        // (setup_system() and the helper) and the rows still go.
        $contextlist = new approved_contextlist($this->student1, 'mod_crucible', [$context->id]);
        provider::delete_data_for_user($contextlist);
        $this->assertDebuggingCalledCount(2);

        $this->assertFalse($DB->record_exists('crucible_attempts', ['id' => $attempt1]));
        $this->assertFalse($DB->record_exists('crucible_task_results', ['attemptid' => $attempt1]));
        $this->assertFalse($DB->record_exists('crucible_grades', ['userid' => $this->student1->id]));
        $this->assertFalse($DB->record_exists('crucible_bulkdeploy_user', ['userid' => $this->student1->id]));
        $this->assertFalse($DB->record_exists('crucible_attempt_users', ['userid' => $this->student1->id]));

        // Student2 owns the attempt student1 had joined; it survives intact.
        $this->assertTrue($DB->record_exists('crucible_attempts', ['id' => $attempt2]));
        $this->assertTrue($DB->record_exists('crucible_task_results', ['attemptid' => $attempt2]));
        $this->assertTrue($DB->record_exists('crucible_grades', ['userid' => $this->student2->id]));
        $this->assertTrue($DB->record_exists('crucible_bulkdeploy_user', ['userid' => $this->student2->id]));

        // Scrubbing the teacher leaves the job but drops the references to them.
        $jobid = $this->get_job($this->crucible1);
        provider::delete_data_for_user(new approved_contextlist($this->teacher, 'mod_crucible', [$context->id]));
        $job = $DB->get_record('crucible_bulkdeploy_job', ['id' => $jobid], '*', MUST_EXIST);
        $this->assertEquals(0, $job->initiatorid);
        $this->assertNull($job->cancelledby);
        $this->assertTrue($DB->record_exists('crucible_bulkdeploy_user', ['jobid' => $jobid]));
    }

    /**
     * Only the listed users are removed from the context.
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $attempt1 = $this->create_user_data($this->crucible1, $this->student1);
        $this->join_attempt($attempt1, $this->student2);
        $attempt2 = $this->create_user_data($this->crucible1, $this->student2);
        $other = $this->create_user_data($this->crucible2, $this->student1);
        $context = $this->context_for($this->crucible1);

        // Remove student2 only: their membership of student1's attempt goes, student1's attempt stays.
        provider::delete_data_for_users(new approved_userlist($context, 'mod_crucible', [$this->student2->id]));
        $this->assertDebuggingCalledCount(2);

        $this->assertFalse($DB->record_exists('crucible_attempts', ['id' => $attempt2]));
        $this->assertFalse($DB->record_exists('crucible_attempt_users', ['userid' => $this->student2->id]));
        $this->assertFalse($DB->record_exists('crucible_grades', ['userid' => $this->student2->id]));
        $this->assertFalse($DB->record_exists('crucible_bulkdeploy_user', ['userid' => $this->student2->id]));

        $this->assertTrue($DB->record_exists('crucible_attempts', ['id' => $attempt1]));
        $this->assertTrue($DB->record_exists('crucible_task_results', ['attemptid' => $attempt1]));
        $this->assertTrue($DB->record_exists('crucible_attempts', ['id' => $other]));
        $this->assertEquals(2, $DB->count_records('crucible_grades', ['userid' => $this->student1->id]));
    }
}
