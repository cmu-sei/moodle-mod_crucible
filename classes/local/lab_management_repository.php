<?php
namespace mod_crucible\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Data access and formatting for the instructor lab management page.
 *
 * @package    mod_crucible
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lab_management_repository {

    /**
     * Get all enrolled users with their latest Crucible attempt state.
     *
     * @param int $crucibleid The Crucible activity instance ID.
     * @param int $courseid The course ID.
     * @param array $rolefilter Optional role IDs to include.
     * @return array User rows with attempt data.
     */
    public function get_enrolled_users_with_state(int $crucibleid, int $courseid, array $rolefilter = []): array {
        global $DB;

        $coursecontext = \context_course::instance($courseid);
        $enrolled = get_enrolled_users($coursecontext, '', 0, 'u.*', null, 0, 0, true);

        if ($rolefilter && !in_array(0, $rolefilter)) {
            $enrolled = array_filter($enrolled, function($user) use ($rolefilter, $coursecontext) {
                $userroles = get_user_roles($coursecontext, $user->id);
                foreach ($userroles as $role) {
                    if (in_array($role->roleid, $rolefilter)) {
                        return true;
                    }
                }
                return false;
            });
        }

        $attempts = $DB->get_records('crucible_attempts', ['crucibleid' => $crucibleid], 'id DESC', 'id, userid');
        foreach ($attempts as $attempt) {
            if (!isset($enrolled[$attempt->userid])) {
                $user = $DB->get_record('user', ['id' => $attempt->userid]);
                if ($user) {
                    $enrolled[$attempt->userid] = $user;
                }
            }
        }

        if (!$enrolled) {
            return [];
        }

        $userids = array_keys($enrolled);
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $params['crucibleid1'] = $crucibleid;
        $params['crucibleid2'] = $crucibleid;
        $params['crucibleid3'] = $crucibleid;
        $params['crucibleid4'] = $crucibleid;

        $sql = "
            SELECT u.id AS userid,
                   u.firstname,
                   u.lastname,
                   u.email,
                   att.id AS attemptid,
                   att.state AS attemptstate,
                   att.score AS attemptscore,
                   att.eventid AS eventid,
                   att.scenarioid AS scenarioid,
                   att.timestart AS attempttimestart,
                   att.timefinish AS attempttimefinish,
                   att.endtime AS attemptendtime,
                   sched.id AS scheduleid,
                   sched.scheduledfor AS scheduledfor,
                   sched.scheduledtimezone AS scheduledtimezone,
                   sched.status AS schedulestatus
              FROM {user} u
         LEFT JOIN {crucible_attempts} att ON att.userid = u.id
                   AND att.crucibleid = :crucibleid1
                   AND att.id = (
                       SELECT MAX(id)
                         FROM {crucible_attempts}
                        WHERE crucibleid = :crucibleid2
                          AND userid = u.id
                   )
         LEFT JOIN {crucible_scheduled_launches} sched ON sched.userid = u.id
                   AND sched.crucibleid = :crucibleid3
                   AND sched.id = (
                       SELECT MAX(id)
                        FROM {crucible_scheduled_launches}
                        WHERE crucibleid = :crucibleid4
                          AND userid = u.id
                          AND status IN ('scheduled', 'failed')
                   )
             WHERE u.id $insql";

        $rows = $DB->get_records_sql($sql, $params);

        $results = [];
        foreach ($enrolled as $user) {
            $results[] = $rows[$user->id] ?? (object) [
                'userid' => $user->id,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
            ];
        }

        return $results;
    }

    /**
     * Format a management table row.
     *
     * @param \stdClass $row The row from get_enrolled_users_with_state().
     * @param int $cmid The course module ID.
     * @return array Render-ready status values.
     */
    public function format_user_state(\stdClass $row, int $cmid): array {
        $statuslabel = get_string('labstatusnone', 'mod_crucible');
        $scheduledtext = '-';
        $detailhtml = null;
        $now = time();

        $attemptid = $row->attemptid ?? null;
        $attemptstate = $row->attemptstate ?? null;
        $schedulestatus = $row->schedulestatus ?? null;

        if (!empty($row->scheduledfor)) {
            $scheduledtext = userdate(
                $row->scheduledfor,
                get_string('strftimedatetime', 'langconfig'),
                $row->scheduledtimezone ?? 99
            );
        }

        if ($schedulestatus === 'scheduled'
            && $attemptstate !== \mod_crucible\crucible_attempt::INPROGRESS) {
            $statuslabel = ($row->scheduledfor > $now)
                ? get_string('labstatusscheduled', 'mod_crucible')
                : get_string('labstatusready', 'mod_crucible');
            $detailhtml = s($statuslabel) . ' <small class="text-muted">('
                . s(get_string('labstatusscheduledfor', 'mod_crucible', $scheduledtext)) . ')</small>';
        } else if ($schedulestatus === 'failed'
            && $attemptstate !== \mod_crucible\crucible_attempt::INPROGRESS) {
            $statuslabel = get_string('labstatusfailed', 'mod_crucible');
            $detailhtml = s($statuslabel) . ' <small class="text-muted">('
                . s(get_string('labstatusscheduledfor', 'mod_crucible', $scheduledtext)) . ')</small>';
        } else if ($attemptid) {
            $statemap = [
                \mod_crucible\crucible_attempt::NOTSTARTED => get_string('labstatusnotstarted', 'mod_crucible'),
                \mod_crucible\crucible_attempt::INPROGRESS => get_string('labstatusactive', 'mod_crucible'),
                \mod_crucible\crucible_attempt::ABANDONED => get_string('labstatusabandoned', 'mod_crucible'),
                \mod_crucible\crucible_attempt::FINISHED => get_string('labstatusfinished', 'mod_crucible'),
            ];
            $statuslabel = $statemap[$attemptstate] ?? s($attemptstate);

            $datefmt = get_string('strftimedatetime', 'langconfig');
            $parts = [];
            if (!empty($row->attempttimestart)) {
                $parts[] = get_string('labstatusstarted', 'mod_crucible', userdate($row->attempttimestart, $datefmt));
            }
            if (!empty($row->attemptendtime)) {
                $parts[] = get_string('labstatusends', 'mod_crucible', userdate($row->attemptendtime, $datefmt));
            }
            if ($parts) {
                $detailhtml = s($statuslabel) . ' <small class="text-muted">(' . s(implode(', ', $parts)) . ')</small>';
            }
        }

        $eventtext = !empty($row->eventid) ? (string) $row->eventid : '-';

        $actionhtml = '-';
        if (!empty($attemptid)) {
            if ($attemptstate === \mod_crucible\crucible_attempt::INPROGRESS) {
                $url = new \moodle_url('/mod/crucible/manageevent.php', ['id' => $cmid, 'attempt' => $attemptid]);
                $actionhtml = \html_writer::link(
                    $url,
                    get_string('managelab', 'mod_crucible'),
                    ['class' => 'btn btn-sm btn-outline-primary', 'target' => '_blank']
                );
            } else {
                $url = new \moodle_url('/mod/crucible/viewattempt.php', ['a' => $attemptid]);
                $actionhtml = \html_writer::link(
                    $url,
                    get_string('viewattempt', 'mod_crucible'),
                    ['class' => 'btn btn-sm btn-outline-secondary', 'target' => '_blank']
                );
            }
        }

        return [
            'status_label' => $statuslabel,
            'status_class' => strtolower($statuslabel),
            'status_html' => $detailhtml ?? s($statuslabel),
            'event_text' => $eventtext,
            'scheduled_text' => $scheduledtext,
            'action_html' => $actionhtml,
        ];
    }
}
