/**
Crucible Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE
MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO
WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER
INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR
MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL.
CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT
TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact
permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release
and unlimited distribution.  Please see Copyright notice for non-US Government
use and distribution.
This Software includes and/or makes use of the following Third-Party Software
subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas
DM20-0196
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/modal', 'core/str'], function($, Ajax, Notification, Modal, Str) {

    var lastParticipantId = 0;
    var checkInterval = null;
    var cmid = null;

    /**
     * Show a modal notification when someone joins the lab
     */
    var showJoinNotification = function(participants, labName) {
        if (participants.length === 0) {
            return;
        }

        var names = participants.map(function(p) { return p.fullname; }).join(', ');
        var message = participants.length === 1
            ? names + ' has joined your lab!'
            : names + ' have joined your lab!';

        Str.get_string('confirm', 'moodle').then(function(confirmString) {
            return Modal.create({
                type: Modal.types.DEFAULT,
                title: 'User Joined Lab',
                body: '<p><strong>' + message + '</strong></p><p>Lab: ' + labName + '</p>',
                large: false
            });
        }).then(function(modal) {
            modal.show();
            // Auto-hide after 5 seconds
            setTimeout(function() {
                modal.hide();
                modal.destroy();
            }, 5000);
            return;
        }).catch(Notification.exception);
    };

    /**
     * Check for new participants
     */
    var checkParticipants = function(labName) {
        Ajax.call([{
            methodname: 'core_get_fragment',
            args: {
                component: 'mod_crucible',
                callback: 'check_participants',
                contextid: 1,
                args: []
            },
            fail: function() {
                // Fallback to direct AJAX call
                $.ajax({
                    url: M.cfg.wwwroot + '/mod/crucible/check_participants.php',
                    method: 'GET',
                    data: {
                        id: cmid,
                        lastattemptid: lastParticipantId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.newparticipants.length > 0) {
                            showJoinNotification(response.newparticipants, labName);
                            lastParticipantId = response.latestid;
                        } else if (response.success) {
                            lastParticipantId = response.latestid;
                        }
                    }
                });
            }
        }]);
    };

    /**
     * Initialize the participant notification system
     */
    var init = function(config) {
        cmid = config.cmid;
        var labName = config.labname || 'this lab';
        var isOwner = config.isowner || false;

        // Only run for lab owners with active labs
        if (!isOwner) {
            return;
        }

        // Check every 10 seconds
        checkInterval = setInterval(function() {
            checkParticipants(labName);
        }, 10000);

        // Initial check after 5 seconds
        setTimeout(function() {
            checkParticipants(labName);
        }, 5000);
    };

    return {
        init: init
    };
});
