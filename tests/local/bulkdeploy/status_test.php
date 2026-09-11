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

namespace mod_crucible\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the bulk deployment status vocabularies.
 *
 * The terminal/non-terminal split drives whether the deploy task keeps polling a row
 * and whether the job is allowed to finish, so both halves are asserted as a complete
 * set. Adding a status without deciding where it belongs is meant to fail here.
 *
 * @package    mod_crucible
 * @category   test
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_crucible\local\bulkdeploy\job_status::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_crucible\local\bulkdeploy\user_status::class)]
final class status_test extends \basic_testcase {

    /**
     * Pins the whole job vocabulary, including which values are terminal, so a new
     * status cannot be introduced without this expectation being updated on purpose.
     */
    public function test_job_status_vocabulary(): void {
        $expected = [
            'QUEUED' => 'queued',
            'RUNNING' => 'running',
            'CANCELLING' => 'cancelling',
            'CANCELLED' => 'cancelled',
            'COMPLETED' => 'completed',
            'FAILED' => 'failed',
            'TERMINAL' => ['cancelled', 'completed', 'failed'],
        ];

        $this->assertSame($expected, (new \ReflectionClass(job_status::class))->getConstants());
    }

    /**
     * Pins the whole per-user vocabulary the same way.
     */
    public function test_user_status_vocabulary(): void {
        $expected = [
            'PENDING' => 'pending',
            'LAUNCHED' => 'launched',
            'CANCELLING' => 'cancelling',
            'READY' => 'ready',
            'SKIPPED' => 'skipped',
            'FAILED' => 'failed',
            'CANCELLED' => 'cancelled',
            'TERMINAL' => ['ready', 'skipped', 'failed', 'cancelled'],
        ];

        $this->assertSame($expected, (new \ReflectionClass(user_status::class))->getConstants());
    }

    /**
     * A job is terminal once it can no longer change on its own. "cancelling" is not:
     * the task still has to run to tear the launched events down.
     *
     * @param string $status Status value to classify.
     * @param bool $expected Whether the status ends the job.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('job_status_provider')]
    public function test_job_status_is_terminal(string $status, bool $expected): void {
        $this->assertSame($expected, job_status::is_terminal($status));
    }

    /**
     * Every declared job status, plus a value that is not one at all.
     *
     * @return array
     */
    public static function job_status_provider(): array {
        return [
            'queued' => [job_status::QUEUED, false],
            'running' => [job_status::RUNNING, false],
            'cancelling' => [job_status::CANCELLING, false],
            'cancelled' => [job_status::CANCELLED, true],
            'completed' => [job_status::COMPLETED, true],
            'failed' => [job_status::FAILED, true],
            'unknown' => ['mod_crucible_not_a_status', false],
        ];
    }

    /**
     * A user row is terminal once the deploy task is done with it. "launched" is not:
     * the event still has to be polled until it is ready or fails.
     *
     * @param string $status Status value to classify.
     * @param bool $expected Whether the status ends work on the row.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('user_status_provider')]
    public function test_user_status_is_terminal(string $status, bool $expected): void {
        $this->assertSame($expected, user_status::is_terminal($status));
    }

    /**
     * Every declared per-user status, plus a value that is not one at all.
     *
     * @return array
     */
    public static function user_status_provider(): array {
        return [
            'pending' => [user_status::PENDING, false],
            'launched' => [user_status::LAUNCHED, false],
            'cancelling' => [user_status::CANCELLING, false],
            'ready' => [user_status::READY, true],
            'skipped' => [user_status::SKIPPED, true],
            'failed' => [user_status::FAILED, true],
            'cancelled' => [user_status::CANCELLED, true],
            'unknown' => ['mod_crucible_not_a_status', false],
        ];
    }
}
