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
 * Unit tests for deleting a Crucible activity's attempts and bulk-deploy state.
 *
 * @package    mod_crucible
 * @category   test
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('crucible_delete_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('crucible_delete_all_attempts')]
#[\PHPUnit\Framework\Attributes\CoversFunction('crucible_delete_attempts')]
#[\PHPUnit\Framework\Attributes\CoversFunction('crucible_end_alloy_events')]
#[\PHPUnit\Framework\Attributes\CoversFunction('crucible_live_event_ids')]
final class delete_test extends \advanced_testcase {
    /** @var \stdClass The course holding both activities. */
    protected $course;

    /** @var \stdClass The activity being deleted. */
    protected $crucible1;

    /** @var \stdClass A second activity, whose data must survive. */
    protected $crucible2;

    /**
     * Build the course and activities shared by every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('alloyapiurl', 'https://alloy.invalid/api', 'crucible');

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->crucible1 = $generator->create_module('crucible', ['course' => $this->course->id]);
        $this->crucible2 = $generator->create_module('crucible', ['course' => $this->course->id]);
    }

    /**
     * Seed an activity with a finished attempt, an in-progress attempt that has an invited user,
     * task results, grades and a bulk-deploy job with one ready and one failed row.
     *
     * The ready row shares its event with the in-progress attempt, the way a bulk-deployed lab
     * later picked up by its student does.
     *
     * @param \stdClass $crucible The activity.
     * @param string $prefix Prefix for the event GUIDs, so activities do not collide.
     * @return array The owner and invitee users, in that order.
     */
    protected function seed(\stdClass $crucible, string $prefix): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $owner = $generator->create_user();
        $invitee = $generator->create_user();
        $now = time();

        $taskid = $DB->insert_record('crucible_tasks', (object)['crucibleid' => $crucible->id, 'name' => 'Check']);
        foreach (['finished' => "$prefix-finished", 'inprogress' => "$prefix-live"] as $state => $eventid) {
            $attemptid = $DB->insert_record('crucible_attempts', (object)[
                'crucibleid' => $crucible->id,
                'userid' => $owner->id,
                'eventid' => $eventid,
                'state' => $state,
                'endtime' => $now + 3600,
                'timestart' => $now,
                'timemodified' => $now,
            ]);
            $DB->insert_record('crucible_task_results', (object)[
                'taskid' => $taskid,
                'attemptid' => $attemptid,
                'score' => 1,
            ]);
        }
        $DB->insert_record('crucible_attempt_users', (object)['attemptid' => $attemptid, 'userid' => $invitee->id]);
        $DB->insert_record('crucible_grades', (object)[
            'crucibleid' => $crucible->id,
            'userid' => $owner->id,
            'grade' => 1,
            'timemodified' => $now,
        ]);

        $jobid = $DB->insert_record('crucible_bulkdeploy_job', (object)[
            'crucibleid' => $crucible->id,
            'courseid' => $this->course->id,
            'initiatorid' => $owner->id,
            'totalusers' => 2,
            'status' => 'completed',
            'timecreated' => $now,
        ]);
        $DB->insert_record('crucible_bulkdeploy_user', (object)[
            'jobid' => $jobid,
            'userid' => $owner->id,
            'eventid' => "$prefix-live",
            'status' => 'ready',
        ]);
        $DB->insert_record('crucible_bulkdeploy_user', (object)[
            'jobid' => $jobid,
            'userid' => $invitee->id,
            'eventid' => "$prefix-failed",
            'status' => 'failed',
        ]);

        return [$owner, $invitee];
    }

    /**
     * Assert that an activity has no attempt, grade or bulk-deploy rows left.
     *
     * @param \stdClass $crucible The activity.
     */
    protected function assert_wiped(\stdClass $crucible): void {
        global $DB;

        $this->assertSame(0, $DB->count_records('crucible_attempts', ['crucibleid' => $crucible->id]));
        $this->assertSame(0, $DB->count_records('crucible_grades', ['crucibleid' => $crucible->id]));
        $this->assertSame(0, $DB->count_records('crucible_bulkdeploy_job', ['crucibleid' => $crucible->id]));
    }

    /**
     * Assert that the second activity's data is untouched.
     */
    protected function assert_other_survives(): void {
        global $DB;

        $this->assertSame(2, $DB->count_records('crucible_attempts', ['crucibleid' => $this->crucible2->id]));
        $this->assertSame(2, $DB->count_records('crucible_task_results'));
        $this->assertSame(1, $DB->count_records('crucible_attempt_users'));
        $this->assertSame(1, $DB->count_records('crucible_grades', ['crucibleid' => $this->crucible2->id]));
        $this->assertSame(2, $DB->count_records('crucible_bulkdeploy_user'));
        $this->assertSame(1, $DB->count_records('crucible_tasks', ['crucibleid' => $this->crucible2->id]));
    }

    /**
     * A stub Alloy client that records each request and answers with a fixed status or throws.
     *
     * @param int|null $httpcode Status to report, or null to throw from delete().
     * @return object
     */
    protected function stub_client(?int $httpcode): object {
        return new class ($httpcode) {
            /** @var array Last response info, read by stop_event(). */
            public $info = [];

            /** @var array URLs passed to delete(). */
            public $urls = [];

            /**
             * Constructor.
             *
             * @param int|null $httpcode Status to report, or null to throw.
             */
            public function __construct(
                /** @var int|null Status to report, or null to throw. */
                private ?int $httpcode
            ) {
            }

            /**
             * Record the request and answer it.
             *
             * @param string $url The request URL.
             * @return string
             */
            public function delete($url) {
                $this->urls[] = $url;
                if ($this->httpcode === null) {
                    throw new \moodle_exception('generalexceptionmessage', 'error', '', 'Alloy unreachable');
                }
                $this->info = ['http_code' => $this->httpcode];
                return '';
            }
        };
    }

    /**
     * Deleting the activity removes its attempts, task results, memberships, grades, bulk-deploy
     * rows and tasks, even with no Alloy client configured.
     */
    public function test_delete_instance_removes_all_activity_data(): void {
        global $DB;

        $this->seed($this->crucible1, 'a');
        $this->seed($this->crucible2, 'b');

        $this->assertTrue(crucible_delete_instance($this->crucible1->id));
        // No OAuth issuer: setup_system() and the helper each log once, and the wipe still runs.
        $this->assertDebuggingCalledCount(2);

        $this->assert_wiped($this->crucible1);
        $this->assertSame(0, $DB->count_records('crucible_tasks', ['crucibleid' => $this->crucible1->id]));
        $this->assertFalse($DB->record_exists('crucible', ['id' => $this->crucible1->id]));
        $this->assert_other_survives();
    }

    /**
     * Only unfinished attempts and live deployments are ended, each event once.
     */
    public function test_delete_all_attempts_ends_live_events_once(): void {
        $this->seed($this->crucible1, 'a');
        $this->seed($this->crucible2, 'b');
        $client = $this->stub_client(204);

        crucible_delete_all_attempts($this->crucible1, $client);

        $this->assertSame(['https://alloy.invalid/api/events/a-live/end'], $client->urls);
        $this->assert_wiped($this->crucible1);
        $this->assert_other_survives();
    }

    /**
     * An Alloy error response is logged and the rows are still deleted.
     */
    public function test_delete_all_attempts_survives_error_response(): void {
        $this->seed($this->crucible1, 'a');
        $this->seed($this->crucible2, 'b');
        $client = $this->stub_client(500);

        crucible_delete_all_attempts($this->crucible1, $client);
        $this->assertDebuggingCalled();

        $this->assertCount(1, $client->urls);
        $this->assert_wiped($this->crucible1);
        $this->assert_other_survives();
    }

    /**
     * An exception from the Alloy client is logged and the rows are still deleted.
     */
    public function test_delete_all_attempts_survives_client_exception(): void {
        $this->seed($this->crucible1, 'a');
        $this->seed($this->crucible2, 'b');
        $client = $this->stub_client(null);

        crucible_delete_all_attempts($this->crucible1, $client);
        $this->assertDebuggingCalled();

        $this->assertCount(1, $client->urls);
        $this->assert_wiped($this->crucible1);
        $this->assert_other_survives();
    }

    /**
     * Nothing to end means no client is resolved and nothing is logged.
     */
    public function test_end_alloy_events_skips_empty_list(): void {
        crucible_end_alloy_events(['', null, '  ']);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Limiting to users finds the events they own, not the event of an attempt they only joined.
     */
    public function test_live_event_ids_limited_to_users(): void {
        [$owner, $invitee] = $this->seed($this->crucible1, 'a');
        $this->seed($this->crucible2, 'b');

        $this->assertSame(['a-live', 'a-live'], crucible_live_event_ids($this->crucible1->id, [$owner->id]));
        $this->assertSame([], crucible_live_event_ids($this->crucible1->id, [$invitee->id]));
        $this->assertSame([], crucible_live_event_ids($this->crucible1->id, []));
        $this->assertSame(['a-live', 'a-live'], crucible_live_event_ids($this->crucible1->id));
    }
}
