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

namespace mod_crucible\task;

use mod_crucible\local\bulkdeploy\job_repository;
use mod_crucible\local\bulkdeploy\job_status;
use mod_crucible\local\bulkdeploy\launcher;
use mod_crucible\local\bulkdeploy\user_status;

defined('MOODLE_INTERNAL') || die();

/**
 * Ad-hoc task that drives a bulk-deploy job to completion in sequential batches.
 * For Crucible, batchsize is always 1 (sequential processing).
 * @package    mod_crucible
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulkdeploy_run extends \core\task\adhoc_task {
    /**
     * Returns the component this task belongs to.
     *
     * @return string The component name.
     */
    public function get_component(): string {
        return 'mod_crucible';
    }

    /**
     * Runs the bulk-deploy job named in the task's custom data.
     */
    public function execute() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/crucible/locallib.php');

        $custom = $this->get_custom_data();
        $jobid = (int) ($custom->jobid ?? 0);
        if ($jobid <= 0) {
            mtrace("bulkdeploy_run: missing jobid in custom_data; aborting");
            return;
        }

        $repo = new job_repository();
        $job = $repo->get_job($jobid);
        if (!$job) {
            mtrace("bulkdeploy_run: job $jobid not found; aborting");
            return;
        }
        if (job_status::is_terminal($job->status)) {
            mtrace("bulkdeploy_run: job $jobid status={$job->status} (terminal); skipping");
            return;
        }

        try {
            $repo->set_job_status($jobid, job_status::RUNNING);
            mtrace("bulkdeploy_run: job $jobid started (batchsize={$job->batchsize})");

            $crucible = $DB->get_record('crucible', ['id' => $job->crucibleid], '*', MUST_EXIST);

            // Build launcher with configurable timeouts
            $pollintervalsec = 10; // Poll every 10 seconds.
            $waitceilingsec = crucible_get_bulkdeploy_wait_timeout() * MINSECS;
            $launcher = new launcher($repo, $pollintervalsec, $waitceilingsec);

            $rows = $repo->get_active_user_rows($jobid);
            $blockingrows = array_filter($rows, function ($row) {
                return $row->status === user_status::LAUNCHED
                    || $row->status === user_status::CANCELLING;
            });
            if ($blockingrows) {
                // A resumed task must reconcile an existing event before
                // starting any pending row. Crucible Terraform workspaces must
                // never provision concurrently.
                $rows = [reset($blockingrows)];
            }
            $userids = array_map(fn($r) => (int)$r->userid, $rows);
            $users = $userids ? $DB->get_records_list('user', 'id', $userids) : [];

            $batch = [];
            $batchno = 0;
            foreach ($rows as $row) {
                $user = $users[$row->userid] ?? null;
                if (!$user) {
                    $repo->set_user_status($row->id, user_status::SKIPPED, 'user not found');
                    continue;
                }
                $batch[] = [
                    'rowid' => (int)$row->id,
                    'user' => $user,
                    'eventid' => (string)($row->eventid ?? ''),
                    'timestarted' => (int)($row->timestarted ?? 0),
                ];

                // Process batch when it reaches batchsize (always 1 for Crucible)
                if (count($batch) >= (int) $job->batchsize) {
                    $batchno++;
                    if (!$this->run_one_batch($repo, $launcher, $jobid, $batchno, $batch, $crucible)) {
                        return;
                    }
                    if ($this->has_blocking_rows($repo, $jobid)) {
                        $this->queue_reconciliation($jobid);
                        mtrace("bulkdeploy_run: job $jobid is waiting for an existing Alloy event");
                        return;
                    }
                    $batch = [];
                }
            }

            // Process remaining users in final batch
            if ($batch) {
                $batchno++;
                if (!$this->run_one_batch($repo, $launcher, $jobid, $batchno, $batch, $crucible)) {
                    return;
                }
            }

            // Check final status
            $current = $repo->get_job($jobid);
            if ($current->status === job_status::CANCELLING) {
                $repo->mark_pending_cancelled($jobid);
                $repo->set_job_status($jobid, job_status::CANCELLED);
                mtrace("bulkdeploy_run: job $jobid cancelled");
            } else if ($current->status === job_status::RUNNING) {
                $active = $repo->get_active_user_rows($jobid);
                if ($active) {
                    $this->queue_reconciliation($jobid);
                    mtrace("bulkdeploy_run: job $jobid has " . count($active)
                        . " active rows; queued reconciliation instead of completing");
                } else {
                    $repo->set_job_status($jobid, job_status::COMPLETED);
                    mtrace("bulkdeploy_run: job $jobid completed");
                }
            }
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            mtrace("bulkdeploy_run: job $jobid FAILED: $msg");
            mtrace($e->getTraceAsString());
            debugging("bulkdeploy_run job $jobid failed: $msg\n" . $e->getTraceAsString(), DEBUG_DEVELOPER);
            $repo->set_job_status($jobid, job_status::FAILED, $msg);
        }
    }

    /**
     * Runs one batch unless the job was cancelled first.
     *
     * @param job_repository $repo Repository for job and user rows.
     * @param launcher $launcher Launcher that processes the batch.
     * @param int $jobid Bulk-deploy job ID.
     * @param int $batchno Batch number, for log output.
     * @param array $batch Batch entries, each ['rowid' => int, 'user' => stdClass].
     * @param \stdClass $crucible Crucible activity record.
     * @return bool False if the job was cancelled before the batch started, otherwise true.
     */
    private function run_one_batch(
        job_repository $repo,
        launcher $launcher,
        int $jobid,
        int $batchno,
        array $batch,
        \stdClass $crucible
    ): bool {
        // Check if job was cancelled before starting batch
        $current = $repo->get_job($jobid);
        if ($current->status === job_status::CANCELLING) {
            $repo->mark_pending_cancelled($jobid);
            $repo->set_job_status($jobid, job_status::CANCELLED);
            mtrace("bulkdeploy_run: job $jobid cancelled before batch $batchno");
            return false;
        }

        mtrace("bulkdeploy_run: job $jobid batch $batchno (" . count($batch) . " users) start");
        $launcher->run_batch($jobid, $batch, $crucible);
        mtrace("bulkdeploy_run: job $jobid batch $batchno end");

        return true;
    }

    /**
     * Schedule a fresh task to reconcile rows that remain active unexpectedly.
     */
    private function queue_reconciliation(int $jobid): void {
        $task = new self();
        $task->set_custom_data((object)['jobid' => $jobid]);
        $task->set_component('mod_crucible');
        $task->set_next_run_time(time() + 10);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Returns whether the job still has an event that must be reconciled before
     * another pending event may start.
     */
    private function has_blocking_rows(job_repository $repo, int $jobid): bool {
        foreach ($repo->get_active_user_rows($jobid) as $row) {
            if (
                $row->status === user_status::LAUNCHED
                || $row->status === user_status::CANCELLING
            ) {
                return true;
            }
        }

        return false;
    }
}
