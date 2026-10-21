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

/**
 * Language file.
 *
 * @package   mod_crucible
 * @copyright 2020 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Crucible';
$string['modulename_help'] = 'Displays Crucible labs and VM consoles';
$string['modulename_link'] = 'mod/crucible/view';
$string['modulenameplural'] = 'Crucibles';
$string['pluginname'] = 'Crucible';

// Plugin settings.
$string['alloyapiurl'] = 'Alloy API Base URL (Server)';
$string['alloyapiclienturl'] = 'Alloy API Base URL (Browser)';
$string['vmapp'] = 'Display Mode';
$string['vmappurl'] = 'VM App Base URL';
$string['playerappurl'] = 'Player Base URL';
$string['steamfitterapiurl'] = 'Steamfitter API URL';
$string['issuerid'] = 'OAUTH2 Issuer';
$string['autocomplete'] = 'Event Template Selection Method';
$string['maxextendinterval'] = 'Maximum event extension interval';
$string['bulkdeploywaittimeout'] = 'Bulk deployment wait timeout';
$string['eventtemplate'] = 'Event Template';
$string['selectname'] = 'Search for an Event Template by name';
$string['showfailed'] = 'Show Failed';

$string['configissuerid'] = 'OAuth2 issuer for authentication with Alloy API. The issuer must be configured in Site administration > Server > OAuth 2 services.';
$string['configissuerid_none'] = 'No OAuth 2 services exist yet. <a href="{$a}">Create one in OAuth 2 services</a>, then return here to select it.';
$string['noissuers'] = 'No OAuth 2 services available';
$string['issuernotconfigured'] = 'Crucible cannot connect to Alloy because no OAuth2 issuer is selected in the Crucible plugin settings. Ask a site administrator to configure it.';
$string['configvmapp'] = 'This determines whether the VM app is embedded or whether a link to player is displayed.';
$string['configvmappurl'] = 'Base URL for VM app instance without trailing /.';
$string['configplayerappurl'] = 'Base URL for Player instance without trailing /.';
$string['configsteamfitterapiurl'] = 'Base URL for Steamfitter API instance without trailing /.';
$string['configalloyapiurl'] = 'Base URL for Alloy API used by server-side PHP (e.g., http://host.docker.internal:4402/api). Without trailing /.';
$string['configalloyapiclienturl'] = 'Base URL for Alloy API used by browser JavaScript (e.g., http://localhost:4402/api). Without trailing /. Leave empty to use same as server URL.';
$string['configeventtemplate'] = 'Event Template GUID to be launched.';
$string['configautocomplete'] = 'Display list of Event Templates in a dropdown or a searchable text box.';
$string['configmaxextendinterval'] = 'Maximum number of minutes an activity may add when extending an event. Defaults to 60 minutes.';
$string['configbulkdeploywaittimeout'] = 'Maximum number of minutes to wait for each user event to become active during a sequential bulk deployment. If Alloy does not report the event active by this deadline, Moodle marks that user deployment failed and starts the next selected user. Reconciliation tasks use the same deadline, so a stuck event cannot queue forever. Defaults to 10 minutes. Values outside 1 to 60 minutes are constrained to that range.';
$string['configshowfailed'] = 'Show failed Events in the history table.';
$string['contentlicense'] = 'Content License';
$string['contentlicense_help'] = 'Select the appropriate content license associated with this content or lab from the dropdown menu.';
$string['showcontentlicense'] = 'Display Content License to Students';
$string['showcontentlicense_help'] = 'If checked, the content license text will be visible to students on the activity page.';

// Activity settings.
$string['vmapp_help'] = 'This determines whether the VM app is emebeded in an iframe or whether a link to the player is displayed';
$string['eventtemplate_help'] = 'This is the Event Template GUID in Alloy.';
$string['eventtemplate'] = 'Alloy Event Template';
$string['pluginadministration'] = 'Crucible administration';
$string['playerlinktext'] = 'Open Player in New Tab';
$string['allowstudentinvites'] = 'Allow students to invite others';
$string['allowstudentinvites_help'] = 'When enabled, students can copy an invitation link for their active lab. Instructors can still invite users when this is disabled.';
$string['clock'] = 'Timer';
$string['configclock'] = 'Style for timer.';
$string['clock_help'] = 'Display no timer, a countup timer, or a countdown timer.';
$string['firstattempt'] = 'First attempt';
$string['lastattempt'] = 'Last completed attempt';
$string['highestattempt'] = 'Highest attempt';
$string['attemptaverage'] = 'Average of all attempts';
$string['grademethod'] = 'Grading method';
$string['grademethod_help'] = 'The grading method defines how the grade for a single attempt of the activity is determined.';
$string['grademethoddesc'] = 'The grading method defines how the grade for a single attempt of the activity is determined.';
$string['extendeventsetting'] = 'Extend Event';
$string['extendeventsetting_help'] = 'Setting this allows the user to extend the lab by the configured interval.';
$string['extendinterval'] = 'Extend interval';
$string['extendinterval_help'] = 'Number of minutes to add when the event is extended.';
$string['extendintervalmax'] = 'The extend interval cannot be greater than {$a} minutes.';
$string['extendintervalpositive'] = 'The extend interval must be at least 1 minute.';

// Time options.
$string['timing'] = 'Timing';
$string['eventopen'] = 'Start Quiz';
$string['eventclose'] = 'Close the quiz';
$string['eventopen_help'] = 'The actitity will not be available until this date.';
$string['eventclose_help'] = 'The activity will not be available after this date';

// History table.
$string['id'] = 'Alloy Event GUID';
$string['status'] = 'Status';
$string['launchdate'] = 'Launch Date';
$string['enddate'] = 'End Date';
$string['historycaption'] = 'History';
// Attempt table.
$string['eventid'] = 'Alloy Event GUID';
$string['state'] = 'State';
$string['timestart'] = 'Time Started';
$string['timefinish'] = 'Time Finished';
$string['tasks'] = 'Tasks';
$string['score'] = 'Score';
$string['username'] = 'Username';

// Events.
$string['eventattemptstarted'] = 'Attempt started';
$string['eventattemptended'] = 'Attempt ended';

// Tasks.
$string['taskcaption'] = 'Tasks';
$string['taskid'] = 'Task ID';
$string['taskdesc'] = 'Task Description';
$string['taskname'] = 'Task Name';
$string['taskresult'] = 'Latest Task Result';
$string['taskaction'] = 'Action';
$string['taskexecute'] = 'Execute Task';
$string['tasknoexecute'] = 'Not Executable';

// View.
$string['eventwithoutattempt'] = 'Event exists but attempt does not exist in moodle db.';
$string['courseorinstanceid'] = 'Either a course id or an instance must be given.';
$string['attemptalreadyexists'] = 'An open attempt already exists for this event';
$string['overallgrade'] = 'Overall Grade';
$string['fullscreen'] = 'Fullscreen VM App';
$string['extendevent'] = 'Extend Event';
$string['reviewtext'] = 'Review Activity Attempts';
$string['managetext'] = 'Review Course Attempts';
$string['durationtext'] = 'Scheduled Duration';
$string['attemptscore'] = 'Attempt Grade: ';
$string['grade'] = 'Grade';
$string['grade_help'] = 'This setting specifies the maximum score for the lab. Set it to 0 to hide grade display for this activity. Grade to pass only sets the passing threshold and does not control grade visibility.';

// Review.
$string['returntext'] = 'Return to Lab';
$string['taskregrade'] = 'Edit Grade';
$string['vmname'] = 'VM Name';
$string['taskcomment'] = 'Comment';

// Tasks.
$string['managetasks'] = 'Manage Tasks';
$string['taskcloseattempt'] = 'Close expired Crucible attempts';
$string['visible'] = 'Visible';
$string['gradable'] = 'Gradable';
$string['multiple'] = 'Multiple VMs';
$string['points'] = 'Points';
$string['visible'] = 'Visible';
$string['visible_help'] = 'If checked, this task will be visible to students.';
$string['gradable'] = 'Gradable';
$string['gradable_help'] = "If checked, this task will contribute to the user's score.";
$string['multiple'] = 'Multiple VMs';
$string['multiple_help'] = 'If checked, this task can be executed on multiple virtual machines whose names match a specified string or mask.';
$string['points'] = 'Points';
$string['points_help'] = 'Set the number of points this task is worth if gradable is enabled.';
$string['enlisterror'] = 'There was an error enlisting the user in the active event.';
$string['studentinvitesdisabled'] = 'Students cannot join this lab using an invitation link.';

// Roles.
$string['crucible:manage'] = 'Manage Crucible activities';
$string['crucible:view'] = 'View Crucible activity information';
$string['crucible:addinstance'] = 'Add a new Crucible activties';

// Privacy.
$string['privacy:metadata:alloy'] = 'To launch a lab, the Crucible activity asks the Alloy API to create an event for the user. Alloy deploys the lab environment through Caster, Player and Steamfitter, where the user is recorded as a member of the event.';
$string['privacy:metadata:alloy:userid'] = 'The user\'s Alloy identifier (their Moodle ID number), sent when a lab is deployed for them.';
$string['privacy:metadata:alloy:username'] = 'The user\'s full name (or username if they have none), sent as their display name in Alloy.';
$string['privacy:metadata:crucible_attempt_users'] = 'The users who have joined another user\'s Crucible lab attempt through an invitation.';
$string['privacy:metadata:crucible_attempt_users:attemptid'] = 'The attempt the user joined.';
$string['privacy:metadata:crucible_attempt_users:userid'] = 'The user who joined the attempt.';
$string['privacy:metadata:crucible_attempts'] = 'Details of each attempt a user makes at a Crucible lab.';
$string['privacy:metadata:crucible_attempts:endtime'] = 'The time at which the Alloy event for this attempt expires.';
$string['privacy:metadata:crucible_attempts:eventid'] = 'The identifier of the Alloy event deployed for this attempt.';
$string['privacy:metadata:crucible_attempts:scenarioid'] = 'The identifier of the Steamfitter scenario deployed for this attempt.';
$string['privacy:metadata:crucible_attempts:score'] = 'The score achieved on this attempt.';
$string['privacy:metadata:crucible_attempts:state'] = 'The state of this attempt.';
$string['privacy:metadata:crucible_attempts:tasks'] = 'The tasks recorded for this attempt.';
$string['privacy:metadata:crucible_attempts:timefinish'] = 'The time at which this attempt was finished.';
$string['privacy:metadata:crucible_attempts:timemodified'] = 'The time at which this attempt was last modified.';
$string['privacy:metadata:crucible_attempts:timestart'] = 'The time at which this attempt was started.';
$string['privacy:metadata:crucible_attempts:userid'] = 'The user who made this attempt.';
$string['privacy:metadata:crucible_bulkdeploy_job'] = 'Details of each bulk deployment of Crucible labs for an activity.';
$string['privacy:metadata:crucible_bulkdeploy_job:cancelledby'] = 'The user who cancelled this bulk deployment.';
$string['privacy:metadata:crucible_bulkdeploy_job:initiatorid'] = 'The user who started this bulk deployment.';
$string['privacy:metadata:crucible_bulkdeploy_job:rolefilter'] = 'The roles this bulk deployment was limited to.';
$string['privacy:metadata:crucible_bulkdeploy_job:status'] = 'The status of this bulk deployment.';
$string['privacy:metadata:crucible_bulkdeploy_job:timecreated'] = 'The time at which this bulk deployment was created.';
$string['privacy:metadata:crucible_bulkdeploy_user'] = 'Details of the lab deployed for each user by a bulk deployment.';
$string['privacy:metadata:crucible_bulkdeploy_user:errormessage'] = 'Any error reported while deploying a lab for this user.';
$string['privacy:metadata:crucible_bulkdeploy_user:eventid'] = 'The identifier of the Alloy event deployed for this user.';
$string['privacy:metadata:crucible_bulkdeploy_user:status'] = 'The status of this user\'s deployment.';
$string['privacy:metadata:crucible_bulkdeploy_user:timecompleted'] = 'The time at which this user\'s deployment completed.';
$string['privacy:metadata:crucible_bulkdeploy_user:timestarted'] = 'The time at which this user\'s deployment started.';
$string['privacy:metadata:crucible_bulkdeploy_user:userid'] = 'The user a lab was deployed for.';
$string['privacy:metadata:crucible_grades'] = 'Details of the overall grade a user has for a Crucible lab.';
$string['privacy:metadata:crucible_grades:grade'] = 'The overall grade for this lab.';
$string['privacy:metadata:crucible_grades:timemodified'] = 'The time at which this grade was last modified.';
$string['privacy:metadata:crucible_grades:userid'] = 'The user this grade belongs to.';
$string['privacy:metadata:crucible_task_results'] = 'The result of each task run against a user\'s attempt.';
$string['privacy:metadata:crucible_task_results:attemptid'] = 'The attempt this result belongs to.';
$string['privacy:metadata:crucible_task_results:comment'] = 'A comment a teacher added to this result.';
$string['privacy:metadata:crucible_task_results:score'] = 'The score given for this result.';
$string['privacy:metadata:crucible_task_results:status'] = 'The status reported by Steamfitter for this task.';
$string['privacy:metadata:crucible_task_results:timemodified'] = 'The time at which this result was last modified.';
$string['privacy:metadata:crucible_task_results:vmname'] = 'The name of the virtual machine the task ran against.';
$string['privacy:path:attempts'] = 'Attempts';
$string['privacy:path:bulkdeploy'] = 'Bulk deployments';
$string['privacy:path:deployment'] = 'Deployment';
$string['privacy:path:grades'] = 'Grades';
$string['privacy:path:joinedattempts'] = 'Joined attempts';
$string['privacy:path:taskresults'] = 'Task results';

$string['notasksavailable'] = 'There are no tasks available for this activity.';
$string['backtocruclanding'] = 'Back to Activity View';
$string['start_attempt_confirm'] = 'Are you sure you want to launch the lab?';
$string['stop_attempt_confirm'] = 'Are you sure you want to end the lab?';

// Task filtering.
$string['filtertasks'] = 'Filter Tasks';
$string['showall'] = 'Show All';
$string['userexecutableonly'] = 'User Executable Only';
$string['nonexecutableonly'] = 'Non-Executable Only';

// Manage Deployments page.
$string['manage_deployments_pageheading'] = 'Manage Deployments';
$string['rolefilter_all'] = 'All Roles';
$string['select_all'] = 'Select All';
$string['deselect_all'] = 'Deselect All';
$string['select_all_help'] = 'Select all users in the table';
$string['deselect_all_help'] = 'Clear all selections';
$string['deploy_selected_now'] = 'Deploy Selected Now';
$string['deploy_selected_help'] = 'Immediately deploy events for selected users';
$string['schedule_selected'] = 'Schedule Selected...';
$string['schedule_selected_help'] = 'Schedule event deployment for selected users at a future time';
$string['cancel_selected'] = 'Cancel Selected';
$string['cancel_selected_help'] = 'Cancel pending/queued deployments for selected users';
$string['end_selected'] = 'End Selected';
$string['end_selected_help'] = 'End active attempts for selected users';
$string['extend_selected'] = 'Extend Selected';
$string['extend_selected_help'] = 'Extend active labs for selected users';
$string['no_users_selected'] = 'No users selected';
$string['deployment_queued'] = 'Deployment queued for {$a} user(s)';
$string['deployment_scheduled'] = 'Deployment scheduled for {$a} user(s)';
$string['deployments_cancelled'] = 'Cancelled {$a} deployment(s)';
$string['deployments_cancel_failed'] = 'Cancellation was requested for {$a} deployment(s); Moodle will retry ending the Alloy event.';
$string['attempts_ended'] = 'Ended {$a} attempt(s)';
$string['attempts_extended'] = 'Extended {$a->count} active attempt(s) by {$a->minutes} minute(s)';
$string['attempts_extend_failed'] = 'Failed to extend {$a} selected active attempt(s)';
$string['viewattempt'] = 'View Attempt';
$string['deploy_confirm_message'] = 'Deploy events for selected users?';
$string['extend_confirm_message'] = 'Choose how many minutes to extend selected active labs.';
$string['end_time'] = 'End Time';
$string['scheduledfor'] = 'Scheduled time';
$string['schedule_past_error'] = 'Scheduled time must be in the future';
$string['extendintervalinvalid'] = 'The activity extend interval is invalid.';

// Status legend - deployment statuses.
$string['status_legend_none'] = 'None: no deployment or attempt for this user.';
$string['status_legend_scheduled'] = 'Scheduled: deployment is queued for a future time.';
$string['status_legend_pending'] = 'Pending: deployment is queued and waiting to launch.';
$string['status_legend_launched'] = 'Launched: deployment has started and the event is being created.';
$string['status_legend_cancelling'] = 'Cancelling: Moodle is retrying the request to end the Alloy event.';
$string['status_legend_failed'] = 'Failed: deployment failed (hover for error).';
$string['status_legend_cancelled'] = 'Cancelled: deployment was cancelled before completing.';
$string['status_legend_inprogress'] = 'In Progress: the lab is active and running.';
$string['status_legend_finished'] = 'Finished: user completed the attempt.';
$string['status_legend_abandoned'] = 'Abandoned: user left the attempt without finishing.';
$string['status_legend_overdue'] = 'Overdue: attempt is past its expiration time.';
$string['status_active_at'] = 'Started: {$a}';
$string['status_ends_at'] = 'Expires: {$a}';
$string['status_started_at'] = 'Started at: {$a}';
$string['status_ended_at'] = 'Ended at: {$a}';
$string['status'] = 'Status';
$string['status_help'] = 'Status values used in this table:

* **None**: no deployment or attempt for this user.
* **Scheduled**: deployment is queued for a future time.
* **Pending**: deployment is queued and waiting to launch.
* **Launched**: deployment has started and the event is being created.
* **Cancelling**: Moodle is retrying the request to end the Alloy event.
* **Ready**: deployment completed, attempt created.
* **Failed**: deployment failed (click ⓘ for error details).
* **Cancelled**: deployment was cancelled before completing.
* **In Progress**: the lab is active and running (click ⓘ for timestamps).
* **Finished**: user completed the attempt.
* **Abandoned**: user left the attempt without finishing.
* **Overdue**: attempt is past its expiration time.';
$string['manage_deploy_running_summary'] = 'Deployments running ({$a->progress}). {$a->link}';
$string['manage_deploy_running_link'] = 'View adhoc task details';
