<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_crucible\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Data access for crucible_bulkdeploy_job and crucible_bulkdeploy_user tables.
 * @package    mod_crucible
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_repository {
    /**
     * Creates a job and one pending row for each user.
     *
     * @param int $crucibleid Crucible activity ID.
     * @param int $courseid Course ID.
     * @param int $initiatorid ID of the user who started the job.
     * @param int $batchsize Batch size recorded on the job.
     * @param string|null $rolefilter Role filter recorded on the job, if any.
     * @param int[] $userids IDs of the users to deploy.
     * @param int|null $scheduledfor Scheduled start time, or null to start now.
     * @return int The new job ID.
     */
    public function create_job(
        int $crucibleid,
        int $courseid,
        int $initiatorid,
        int $batchsize,
        ?string $rolefilter,
        array $userids,
        ?int $scheduledfor = null
    ): int {
        global $DB;
        $now = time();
        $job = (object) [
            'crucibleid'    => $crucibleid,
            'courseid'      => $courseid,
            'initiatorid'   => $initiatorid,
            'batchsize'     => $batchsize,
            'rolefilter'    => $rolefilter,
            'totalusers'    => count($userids),
            'status'        => job_status::QUEUED,
            'timecreated'   => $now,
            'scheduledfor'  => $scheduledfor,
        ];
        $jobid = $DB->insert_record('crucible_bulkdeploy_job', $job);

        $rows = [];
        foreach ($userids as $userid) {
            $rows[] = (object) [
                'jobid'   => $jobid,
                'userid'  => $userid,
                'status'  => user_status::PENDING,
            ];
        }
        if ($rows) {
            $DB->insert_records('crucible_bulkdeploy_user', $rows);
        }
        return $jobid;
    }

    /**
     * Gets a job record.
     *
     * @param int $jobid Job ID.
     * @return \stdClass|null The job record, or null if it does not exist.
     */
    public function get_job(int $jobid): ?\stdClass {
        global $DB;
        $row = $DB->get_record('crucible_bulkdeploy_job', ['id' => $jobid]);
        return $row ?: null;
    }

    /**
     * Gets all user rows of a job, in ID order.
     *
     * @param int $jobid Job ID.
     * @return \stdClass[] User rows indexed by row ID.
     */
    public function get_user_rows(int $jobid): array {
        global $DB;
        return $DB->get_records('crucible_bulkdeploy_user', ['jobid' => $jobid], 'id ASC');
    }

    /**
     * Look up the current status of the supplied user-row IDs.
     *
     * @param int[] $rowids
     * @return array<int,string> map of rowid => status; rowids that don't exist are omitted
     */
    public function get_user_statuses(array $rowids): array {
        global $DB;
        if (!$rowids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($rowids, SQL_PARAMS_NAMED);
        $rows = $DB->get_records_select(
            'crucible_bulkdeploy_user',
            "id $insql",
            $params,
            '',
            'id, status'
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id] = $r->status;
        }
        return $out;
    }

    /**
     * Gets the rows of a job whose status is not yet terminal.
     *
     * @param int $jobid Job ID.
     * @return \stdClass[] rows whose status is not yet terminal
     */
    public function get_active_user_rows(int $jobid): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(user_status::TERMINAL, SQL_PARAMS_NAMED, 'st', false);
        $params['jobid'] = $jobid;
        return $DB->get_records_select(
            'crucible_bulkdeploy_user',
            "jobid = :jobid AND status $insql",
            $params,
            'id ASC'
        );
    }

    /**
     * Sets a job's status and updates its start, completion and error fields to match.
     *
     * @param int $jobid Job ID.
     * @param string $status New job status.
     * @param string|null $errormessage Error message to record, if any (truncated to 255 characters).
     */
    public function set_job_status(int $jobid, string $status, ?string $errormessage = null): void {
        global $DB;
        $now = time();
        $update = (object) ['id' => $jobid, 'status' => $status];
        if ($status === job_status::RUNNING) {
            $existing = $this->get_job($jobid);
            if ($existing && empty($existing->timestarted)) {
                $update->timestarted = $now;
            }
        }
        if (job_status::is_terminal($status)) {
            $update->timecompleted = $now;
        }
        if ($errormessage !== null) {
            $update->errormessage = self::truncate255($errormessage);
        }
        $DB->update_record('crucible_bulkdeploy_job', $update);
    }

    /**
     * Records who cancelled a job and when.
     *
     * @param int $jobid Job ID.
     * @param int $userid ID of the user who cancelled the job.
     */
    public function set_job_cancelled_by(int $jobid, int $userid): void {
        global $DB;
        $DB->update_record('crucible_bulkdeploy_job', (object) [
            'id' => $jobid,
            'cancelledby' => $userid,
            'timecancelled' => time(),
        ]);
    }

    /**
     * Sets a user row's status and updates its start, completion, error and event fields to match.
     *
     * @param int $rowid User row ID.
     * @param string $status New row status.
     * @param string|null $errormessage Error message to record, if any (truncated to 255 characters).
     * @param string|null $eventid Alloy event ID to record, if any.
     */
    public function set_user_status(int $rowid, string $status, ?string $errormessage = null, ?string $eventid = null): void {
        global $DB;
        $now = time();
        $update = (object) ['id' => $rowid, 'status' => $status];
        if ($status === user_status::LAUNCHED) {
            $existing = $DB->get_record('crucible_bulkdeploy_user', ['id' => $rowid], 'timestarted');
            if ($existing && empty($existing->timestarted)) {
                $update->timestarted = $now;
            }
        }
        if (user_status::is_terminal($status)) {
            $update->timecompleted = $now;
        }
        if ($errormessage !== null) {
            $update->errormessage = self::truncate255($errormessage);
        }
        if ($eventid !== null) {
            $update->eventid = $eventid;
        }
        $DB->update_record('crucible_bulkdeploy_user', $update);
    }

    /**
     * Records a newly-created Alloy event only while the row is still pending.
     *
     * This prevents a concurrent cancellation from being overwritten after
     * start_event() returns an event ID.
     *
     * @param int $rowid Bulk deployment user-row ID.
     * @param string $eventid Newly-created Alloy event ID.
     * @return bool Whether the row is still launched with this event ID.
     */
    public function mark_launched_if_pending(int $rowid, string $eventid): bool {
        global $DB;

        $now = time();
        $DB->execute(
            "UPDATE {crucible_bulkdeploy_user}
                SET status = :launched,
                    eventid = :eventid,
                    timestarted = CASE
                        WHEN timestarted IS NULL OR timestarted = 0 THEN :timestarted
                        ELSE timestarted
                    END
              WHERE id = :rowid AND status = :pending",
            [
                'launched' => user_status::LAUNCHED,
                'eventid' => $eventid,
                'timestarted' => $now,
                'rowid' => $rowid,
                'pending' => user_status::PENDING,
            ]
        );

        $row = $DB->get_record(
            'crucible_bulkdeploy_user',
            ['id' => $rowid],
            'status, eventid',
            IGNORE_MISSING
        );

        return $row
            && $row->status === user_status::LAUNCHED
            && $row->eventid === $eventid;
    }

    /**
     * Marks every still-pending row of a job as cancelled.
     *
     * @param int $jobid Job ID.
     */
    public function mark_pending_cancelled(int $jobid): void {
        global $DB;
        $DB->execute(
            "UPDATE {crucible_bulkdeploy_user}
                SET status = :newstatus, timecompleted = :tc
              WHERE jobid = :jobid AND status = :oldstatus",
            [
                'newstatus' => user_status::CANCELLED,
                'tc'        => time(),
                'jobid'     => $jobid,
                'oldstatus' => user_status::PENDING,
            ]
        );
    }

    /**
     * Counts a job's user rows for each status.
     *
     * @param int $jobid Job ID.
     * @return int[] Map of status to row count; statuses with no rows are omitted.
     */
    public function count_user_rows_by_status(int $jobid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT status, COUNT(*) AS n
               FROM {crucible_bulkdeploy_user}
              WHERE jobid = ?
              GROUP BY status",
            [$jobid]
        );
        $counts = [];
        foreach ($rows as $r) {
            $counts[$r->status] = (int) $r->n;
        }
        return $counts;
    }

    /**
     * Truncates a string to at most 255 characters, ending in '...' when cut.
     *
     * @param string $s String to truncate.
     * @return string The original string or its truncated form.
     */
    private static function truncate255(string $s): string {
        return strlen($s) <= 255 ? $s : substr($s, 0, 252) . '...';
    }
}
