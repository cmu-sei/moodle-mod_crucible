<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->dirroot/mod/crucible/lib.php");

$id = required_param('id', PARAM_INT); // Course module ID
$lastattemptid = optional_param('lastattemptid', 0, PARAM_INT); // Last known attempt ID

require_login();

$cm = get_coursemodule_from_id('crucible', $id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);
require_capability('mod/crucible:view', $context);

header('Content-Type: application/json');

// Debug logging
error_log("check_participants.php called by user {$USER->id} for cm {$cm->id}, instance {$cm->instance}, last id: {$lastattemptid}");

// Get all users who have joined attempts owned by the current user
$participants = $DB->get_records_sql(
    "SELECT cau.id, cau.attemptid, cau.userid, u.firstname, u.lastname, u.username
     FROM {crucible_attempt_users} cau
     JOIN {crucible_attempts} ca ON ca.id = cau.attemptid
     JOIN {user} u ON u.id = cau.userid
     WHERE ca.crucibleid = :crucibleid
     AND ca.state = 'inprogress'
     AND ca.userid = :ownerid
     AND cau.userid != :currentuserid
     ORDER BY cau.id DESC",
    [
        'crucibleid' => $cm->instance,
        'ownerid' => $USER->id,
        'currentuserid' => $USER->id
    ]
);

error_log("Found " . count($participants) . " participants");

$newparticipants = [];
foreach ($participants as $participant) {
    if ($participant->id > $lastattemptid) {
        $newparticipants[] = [
            'id' => $participant->id,
            'attemptid' => $participant->attemptid,
            'fullname' => fullname($participant),
            'username' => $participant->username
        ];
    }
}

$result = [
    'success' => true,
    'newparticipants' => $newparticipants,
    'latestid' => !empty($participants) ? reset($participants)->id : $lastattemptid,
    'debug' => [
        'total_participants' => count($participants),
        'new_count' => count($newparticipants),
        'lastattemptid' => $lastattemptid
    ]
];

error_log("Returning: " . json_encode($result));
echo json_encode($result);
