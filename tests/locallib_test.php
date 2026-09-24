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
require_once($CFG->dirroot . '/mod/crucible/locallib.php');

/**
 * Unit tests for the pure helpers in mod_crucible locallib.php.
 *
 * Only the functions that need neither the Alloy API nor an OAuth client are covered
 * here: the setting readers, the active event filter and the sort comparators.
 *
 * @package    mod_crucible
 * @category   test
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('crucible_configure_api_client')]
#[\PHPUnit\Framework\Attributes\CoversFunction('crucible_get_max_extend_interval')]
#[\PHPUnit\Framework\Attributes\CoversFunction('crucible_get_bulkdeploy_wait_timeout')]
#[\PHPUnit\Framework\Attributes\CoversFunction('get_active_events')]
#[\PHPUnit\Framework\Attributes\CoversFunction('tasksort')]
#[\PHPUnit\Framework\Attributes\CoversFunction('end_date')]
#[\PHPUnit\Framework\Attributes\CoversFunction('launchDate')]
#[\PHPUnit\Framework\Attributes\CoversFunction('crucible_parse_alloy_date')]
final class locallib_test extends \advanced_testcase {

    /**
     * A site that has never visited the settings page must still get a usable limit,
     * and a junk value must not turn into a zero-minute maximum that blocks extending.
     *
     * @param mixed $config Value to store in the plugin setting, or null to leave it unset.
     * @param int $expected Minutes crucible_get_max_extend_interval() should report.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('max_extend_interval_provider')]
    public function test_crucible_get_max_extend_interval($config, int $expected): void {
        $this->resetAfterTest();

        if ($config !== null) {
            set_config('maxextendinterval', $config, 'crucible');
        }

        $this->assertSame($expected, crucible_get_max_extend_interval());
    }

    /**
     * Stored setting values and the maximum they should produce.
     *
     * @return array
     */
    public static function max_extend_interval_provider(): array {
        return [
            'unset falls back to the default' => [null, CRUCIBLE_DEFAULT_EXTEND_INTERVAL],
            'zero falls back to the default' => [0, CRUCIBLE_DEFAULT_EXTEND_INTERVAL],
            'negative falls back to the default' => [-15, CRUCIBLE_DEFAULT_EXTEND_INTERVAL],
            'positive value is used' => [30, 30],
            'large positive value is not capped' => [1440, 1440],
        ];
    }

    /**
     * The default is spelled out here as well as in locallib.php: an accidental change
     * to the constant would otherwise pass unnoticed.
     */
    public function test_extend_interval_default_is_sixty_minutes(): void {
        $this->assertSame(60, CRUCIBLE_DEFAULT_EXTEND_INTERVAL);
    }

    /**
     * This timeout bounds how long the deploy task waits on one user's event, so it is
     * clamped rather than trusted: an unbounded value would hold the cron worker.
     *
     * @param mixed $config Value to store in the plugin setting, or null to leave it unset.
     * @param int $expected Minutes crucible_get_bulkdeploy_wait_timeout() should report.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('bulkdeploy_wait_timeout_provider')]
    public function test_crucible_get_bulkdeploy_wait_timeout($config, int $expected): void {
        $this->resetAfterTest();

        if ($config !== null) {
            set_config('bulkdeploywaittimeout', $config, 'crucible');
        }

        $this->assertSame($expected, crucible_get_bulkdeploy_wait_timeout());
    }

    /**
     * Stored setting values and the timeout they should produce.
     *
     * @return array
     */
    public static function bulkdeploy_wait_timeout_provider(): array {
        return [
            'unset falls back to the default' => [null, CRUCIBLE_DEFAULT_BULKDEPLOY_WAIT_TIMEOUT],
            'zero falls back to the default' => [0, CRUCIBLE_DEFAULT_BULKDEPLOY_WAIT_TIMEOUT],
            'negative falls back to the default' => [-1, CRUCIBLE_DEFAULT_BULKDEPLOY_WAIT_TIMEOUT],
            'lowest allowed value passes through' => [1, 1],
            'mid range value passes through' => [15, 15],
            'highest allowed value passes through' => [60, 60],
            'above range is clamped to sixty' => [90, 60],
        ];
    }

    /**
     * The default is spelled out here as well as in locallib.php.
     */
    public function test_bulkdeploy_wait_timeout_default_is_ten_minutes(): void {
        $this->assertSame(10, CRUCIBLE_DEFAULT_BULKDEPLOY_WAIT_TIMEOUT);
    }

    /**
     * No history means "we could not tell", which the callers distinguish from
     * "nothing is running", so null has to survive the call.
     */
    public function test_get_active_events_passes_null_through(): void {
        $this->assertNull(get_active_events(null));
    }

    /**
     * Anything still being built, running or torn down counts as active; an event the
     * user has to relaunch does not. Keeping a finished event in this list would make
     * the view offer a link into an event that no longer exists.
     */
    public function test_get_active_events_keeps_only_the_live_statuses(): void {
        $history = [
            ['id' => 'a', 'status' => 'Active'],
            ['id' => 'b', 'status' => 'Creating'],
            ['id' => 'c', 'status' => 'Planning'],
            ['id' => 'd', 'status' => 'Applying'],
            ['id' => 'e', 'status' => 'Ending'],
            ['id' => 'f', 'status' => 'Expired'],
            ['id' => 'g', 'status' => 'Failed'],
        ];

        $active = get_active_events($history);

        $this->assertSame(['a', 'b', 'c', 'd', 'e'], array_column($active, 'id'));
    }

    /**
     * The API rows arrive as arrays but the templates and callers use property access,
     * so the filter has to hand back objects.
     */
    public function test_get_active_events_returns_objects(): void {
        $active = get_active_events([['id' => 'a', 'status' => 'Active']]);

        $this->assertCount(1, $active);
        $this->assertInstanceOf(\stdClass::class, $active[0]);
        $this->assertSame('Active', $active[0]->status);
    }

    /**
     * A history with nothing live in it is an empty list, not null: the caller has an
     * answer, it is just that no event is running.
     */
    public function test_get_active_events_returns_empty_array_when_nothing_is_live(): void {
        $history = [
            ['id' => 'a', 'status' => 'Expired'],
            ['id' => 'b', 'status' => 'Failed'],
        ];

        $this->assertSame([], get_active_events($history));
    }

    /**
     * usort() only needs the sign of the return value, so that is what is asserted:
     * the comparators must order tasks by name and report equality as zero.
     */
    public function test_tasksort_compares_task_names(): void {
        $apply = (object)['name' => 'apply'];
        $verify = (object)['name' => 'verify'];

        $this->assertLessThan(0, tasksort($apply, $verify));
        $this->assertGreaterThan(0, tasksort($verify, $apply));
        $this->assertSame(0, tasksort($apply, (object)['name' => 'apply']));
    }

    /**
     * The comparator exists to give the task list a stable alphabetical order.
     */
    public function test_tasksort_sorts_a_task_list(): void {
        $tasks = [
            (object)['name' => 'verify'],
            (object)['name' => 'apply'],
            (object)['name' => 'plan'],
        ];

        usort($tasks, 'tasksort');

        $this->assertSame(['apply', 'plan', 'verify'], array_column($tasks, 'name'));
    }

    /**
     * Event rows are compared by their ISO end date, which sorts correctly as text.
     */
    public function test_end_date_compares_end_dates(): void {
        $earlier = ['endDate' => '2026-01-01T00:00:00'];
        $later = ['endDate' => '2026-06-01T00:00:00'];

        $this->assertLessThan(0, end_date($earlier, $later));
        $this->assertGreaterThan(0, end_date($later, $earlier));
        $this->assertSame(0, end_date($earlier, ['endDate' => '2026-01-01T00:00:00']));
    }

    /**
     * A row without an end date is treated as the empty string, so it sorts first
     * instead of raising a notice mid-render.
     */
    public function test_end_date_tolerates_a_missing_key(): void {
        $dated = ['endDate' => '2026-01-01T00:00:00'];

        $this->assertGreaterThan(0, end_date($dated, []));
        $this->assertLessThan(0, end_date([], $dated));
        $this->assertSame(0, end_date([], []));
    }

    /**
     * Sorting a history by end date is how the view finds the most recent event.
     */
    public function test_end_date_sorts_a_history(): void {
        $history = [
            ['endDate' => '2026-06-01T00:00:00'],
            [],
            ['endDate' => '2026-01-01T00:00:00'],
        ];

        usort($history, 'end_date');

        $dates = array_map(static fn($row) => $row['endDate'] ?? '', $history);

        $this->assertSame(['', '2026-01-01T00:00:00', '2026-06-01T00:00:00'], $dates);
    }

    /**
     * Same contract as end_date(), against the launch date key.
     */
    public function test_launchdate_compares_launch_dates(): void {
        $earlier = ['launchDate' => '2026-01-01T00:00:00'];
        $later = ['launchDate' => '2026-06-01T00:00:00'];

        $this->assertLessThan(0, launchDate($earlier, $later));
        $this->assertGreaterThan(0, launchDate($later, $earlier));
        $this->assertSame(0, launchDate($earlier, ['launchDate' => '2026-01-01T00:00:00']));
    }

    /**
     * A row that has not been launched yet has no launch date at all.
     */
    public function test_launchdate_tolerates_a_missing_key(): void {
        $dated = ['launchDate' => '2026-01-01T00:00:00'];

        $this->assertGreaterThan(0, launchDate($dated, []));
        $this->assertLessThan(0, launchDate([], $dated));
        $this->assertSame(0, launchDate([], []));
    }

    /**
     * Sorting a history by launch date puts the never-launched rows first.
     */
    public function test_launchdate_sorts_a_history(): void {
        $history = [
            ['launchDate' => '2026-06-01T00:00:00'],
            [],
            ['launchDate' => '2026-01-01T00:00:00'],
        ];

        usort($history, 'launchDate');

        $dates = array_map(static fn($row) => $row['launchDate'] ?? '', $history);

        $this->assertSame(['', '2026-01-01T00:00:00', '2026-06-01T00:00:00'], $dates);
    }

    /**
     * Read the private options of a \curl instance.
     *
     * @param \curl $client Client to inspect.
     * @return array The cURL options in force.
     */
    private function curl_options(\curl $client): array {
        $options = \Closure::bind(
            static function (\curl $client): array {
                return (array) $client->options;
            },
            null,
            \curl::class
        );
        return $options($client);
    }

    /**
     * The API token must not travel over a connection nobody has authenticated.
     */
    public function test_the_api_client_verifies_the_peer_certificate(): void {
        $this->resetAfterTest(true);

        // Core's OAuth client inherits these defaults from \curl: verification off, and up to
        // ten redirects replaying the request headers. This is what the fix is for.
        $client = new \curl();
        $this->assertSame(0, $this->curl_options($client)['CURLOPT_SSL_VERIFYPEER']);

        crucible_configure_api_client($client);

        $options = $this->curl_options($client);
        $this->assertSame(1, $options['CURLOPT_SSL_VERIFYPEER']);
        $this->assertSame(2, $options['CURLOPT_SSL_VERIFYHOST']);
    }

    /**
     * Alloy calls run during page rendering and in cron, so they are bounded.
     */
    public function test_the_api_client_requests_are_time_bounded(): void {
        $this->resetAfterTest(true);
        $client = new \curl();

        crucible_configure_api_client($client);

        $options = $this->curl_options($client);
        $this->assertSame(5, $options['CURLOPT_CONNECTTIMEOUT']);
        $this->assertSame(15, $options['CURLOPT_TIMEOUT']);
    }

    /**
     * A failed client setup returns falsy, and configuring it must not fatal.
     */
    public function test_configuring_a_missing_client_is_harmless(): void {
        $this->resetAfterTest(true);

        $this->assertFalse(crucible_configure_api_client(false));
        $this->assertNull(crucible_configure_api_client(null));
    }

    /**
     * An event that is still deploying has no dates yet. The clock must get 0 for
     * those, not the current time, or it announces the lab as expired while planning.
     *
     * @param string|null $date Date as Alloy returns it.
     * @param int $expected Timestamp crucible_parse_alloy_date() should return.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('alloy_date_provider')]
    public function test_crucible_parse_alloy_date(?string $date, int $expected): void {
        $this->assertSame($expected, crucible_parse_alloy_date($date));
    }

    /**
     * Alloy dates, with and without the UTC designator, and the not-yet-deployed cases.
     *
     * @return array
     */
    public static function alloy_date_provider(): array {
        return [
            'not deployed yet' => [null, 0],
            'empty' => ['', 0],
            'utc designator' => ['2026-09-23T15:00:00Z', 1790175600],
            'no designator is read as utc' => ['2026-09-23T15:00:00', 1790175600],
            'fractional seconds' => ['2026-09-23T15:00:00.123456', 1790175600],
            'unparseable' => ['not a date', 0],
        ];
    }
}
