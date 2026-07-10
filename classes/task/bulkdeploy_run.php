<?php
namespace mod_crucible\task;

use mod_crucible\local\bulkdeploy\job_repository;
use mod_crucible\local\bulkdeploy\job_status;
use mod_crucible\local\bulkdeploy\launcher;
use mod_crucible\local\bulkdeploy\user_status;

defined('MOODLE_INTERNAL') || die();

/**
 * Ad-hoc task that drives a bulk-deploy job to completion in sequential batches.
 * For Crucible, batchsize is always 1 (sequential processing).
 */
class bulkdeploy_run extends \core\task\adhoc_task {

    public function get_component(): string {
        return 'mod_crucible';
    }

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
            $pollintervalsec = 10; // Poll every 10 seconds
            $waitceilingsec = 600; // 10 minute timeout per user
            $launcher = new launcher($repo, $pollintervalsec, $waitceilingsec);

            $rows = $repo->get_active_user_rows($jobid);
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
                $batch[] = ['rowid' => (int) $row->id, 'user' => $user];

                // Process batch when it reaches batchsize (always 1 for Crucible)
                if (count($batch) >= (int) $job->batchsize) {
                    $batchno++;
                    if (!$this->run_one_batch($repo, $launcher, $jobid, $batchno, $batch, $crucible)) {
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
                $repo->set_job_status($jobid, job_status::COMPLETED);
                mtrace("bulkdeploy_run: job $jobid completed");
            }
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            mtrace("bulkdeploy_run: job $jobid FAILED: $msg");
            mtrace($e->getTraceAsString());
            debugging("bulkdeploy_run job $jobid failed: $msg\n" . $e->getTraceAsString(), DEBUG_DEVELOPER);
            $repo->set_job_status($jobid, job_status::FAILED, $msg);
        }
    }

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
}
