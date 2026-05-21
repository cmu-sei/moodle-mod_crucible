<?php
namespace mod_crucible\local\bulkdeploy;

defined('MOODLE_INTERNAL') || die();

/**
 * Data access for manage deployments page.
 */
class management_repository {

    /**
     * Get all enrolled users with their current deployment/attempt state.
     *
     * @param int $crucibleid Crucible activity ID
     * @param int $courseid Course ID
     * @param array $rolefilter Array of role IDs (empty = all roles)
     * @return array Array of user objects with deployment/attempt info
     */
    public function get_enrolled_users_with_state(
        int $crucibleid,
        int $courseid,
        array $rolefilter = []
    ): array {
        global $DB;

        $coursecontext = \context_course::instance($courseid);
        $enrolled = get_enrolled_users($coursecontext, '', 0, 'u.*', null, 0, 0, true);

        if ($rolefilter && !in_array(0, $rolefilter)) {
            // Filter by role assignments (skip if "All roles" is selected)
            $enrolled = array_filter($enrolled, function($u) use ($rolefilter, $coursecontext) {
                $userroles = get_user_roles($coursecontext, $u->id);
                foreach ($userroles as $role) {
                    if (in_array($role->roleid, $rolefilter)) {
                        return true;
                    }
                }
                return false;
            });
        }

        // Add users with attempts who aren't enrolled (e.g., instructor previews)
        $attempts = $DB->get_records('crucible_attempts', ['crucibleid' => $crucibleid], 'id DESC', 'id, userid, state, eventid');
        foreach ($attempts as $att) {
            if (!isset($enrolled[$att->userid])) {
                $user = $DB->get_record('user', ['id' => $att->userid]);
                if ($user) {
                    $enrolled[$att->userid] = $user;
                }
            }
        }

        if (empty($enrolled)) {
            return [];
        }

        $userids = array_keys($enrolled);
        [$insql1, $params1] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid1');

        $sql = "
            SELECT u.id AS userid,
                   u.firstname,
                   u.lastname,
                   u.email,
                   att.id AS attemptid,
                   att.state AS attemptstate,
                   att.score AS attemptscore,
                   att.eventid AS attemptgamespaceid,
                   att.timestart AS attempttimestart,
                   att.endtime AS attemptendtime,
                   bd.id AS deployrowid,
                   bd.status AS deploystatus,
                   bd.eventid AS deploygamespaceid,
                   bd.errormessage AS deployerror,
                   bdj.scheduledfor AS scheduledfor
              FROM {user} u
         LEFT JOIN {crucible_attempts} att ON att.userid = u.id
                   AND att.crucibleid = :crucibleid1
                   AND att.id = (
                       SELECT MAX(id)
                       FROM {crucible_attempts}
                       WHERE crucibleid = :crucibleid2
                       AND userid = u.id
                   )
         LEFT JOIN {crucible_bulkdeploy_user} bd ON bd.userid = u.id
                   AND bd.id = (
                       SELECT MAX(bdu.id)
                       FROM {crucible_bulkdeploy_user} bdu
                       INNER JOIN {crucible_bulkdeploy_job} bdj ON bdj.id = bdu.jobid
                       WHERE bdj.crucibleid = :crucibleid3
                       AND bdu.userid = u.id
                   )
         LEFT JOIN {crucible_bulkdeploy_job} bdj ON bdj.id = bd.jobid
             WHERE u.id $insql1
        ";

        $params = $params1;
        $params['crucibleid1'] = $crucibleid;
        $params['crucibleid2'] = $crucibleid;
        $params['crucibleid3'] = $crucibleid;

        $rows = $DB->get_records_sql($sql, $params);

        $results = [];
        foreach ($enrolled as $user) {
            $row = $rows[$user->id] ?? (object) [
                'userid' => $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
            ];
            $results[] = $row;
        }

        return $results;
    }

    /**
     * Get active jobs for an activity (QUEUED, RUNNING, CANCELLING).
     *
     * @param int $crucibleid Crucible activity ID
     * @return array Array of job records
     */
    public function get_active_jobs(int $crucibleid): array {
        global $DB;

        $active = [job_status::QUEUED, job_status::RUNNING, job_status::CANCELLING];
        [$insql, $params] = $DB->get_in_or_equal($active, SQL_PARAMS_NAMED);
        $params['crucibleid'] = $crucibleid;

        return $DB->get_records_select(
            'crucible_bulkdeploy_job',
            "crucibleid = :crucibleid AND status $insql",
            $params,
            'id DESC'
        );
    }

    /**
     * Get scheduled jobs (QUEUED with scheduledfor > now).
     *
     * @param int $crucibleid Crucible activity ID
     * @return array Array of scheduled job records
     */
    public function get_scheduled_jobs(int $crucibleid): array {
        global $DB;

        $now = time();
        return $DB->get_records_select(
            'crucible_bulkdeploy_job',
            'crucibleid = :crucibleid AND status = :status AND scheduledfor > :now',
            [
                'crucibleid' => $crucibleid,
                'status' => job_status::QUEUED,
                'now' => $now,
            ],
            'scheduledfor ASC'
        );
    }

    /**
     * Get job progress counts.
     *
     * @param int $jobid Job ID
     * @return \stdClass Object with ready, failed, pending, etc. counts
     */
    public function get_job_progress(int $jobid): \stdClass {
        $repo = new job_repository();
        $counts = $repo->count_user_rows_by_status($jobid);

        return (object) [
            'ready' => $counts[user_status::READY] ?? 0,
            'failed' => $counts[user_status::FAILED] ?? 0,
            'pending' => $counts[user_status::PENDING] ?? 0,
            'launched' => $counts[user_status::LAUNCHED] ?? 0,
            'skipped' => $counts[user_status::SKIPPED] ?? 0,
            'cancelled' => $counts[user_status::CANCELLED] ?? 0,
        ];
    }
}
