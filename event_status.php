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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Returns the current Alloy event status through Moodle.
 *
 * @package    mod_crucible
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/crucible/locallib.php');

$cmid = required_param('cmid', PARAM_INT);
$eventid = required_param('eventid', PARAM_ALPHANUMEXT);
require_sesskey();

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'crucible');
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/crucible:view', $context);

$PAGE->set_url(new moodle_url('/mod/crucible/event_status.php', ['cmid' => $cmid]));
$PAGE->set_context($context);

$params = [
    'crucibleid' => $cm->instance,
    'eventid' => $eventid,
    'ownerid' => $USER->id,
    'memberid' => $USER->id,
];
$sql = "SELECT ca.id
          FROM {crucible_attempts} ca
     LEFT JOIN {crucible_attempt_users} cau ON cau.attemptid = ca.id
         WHERE ca.crucibleid = :crucibleid
           AND ca.eventid = :eventid
           AND (ca.userid = :ownerid OR cau.userid = :memberid)";

if (!$DB->record_exists_sql($sql, $params)) {
    require_capability('mod/crucible:manage', $context);
}

try {
    $client = setup();
    if (!$client) {
        throw new RuntimeException('Unable to authenticate with Alloy');
    }

    $event = get_event($client, $eventid);
    if (!$event) {
        throw new RuntimeException('Unable to retrieve Alloy event');
    }

    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($event);
} catch (\Throwable $e) {
    debugging('Could not retrieve Alloy event status: ' . $e->getMessage(), DEBUG_DEVELOPER);
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unable to retrieve Alloy event status']);
}
