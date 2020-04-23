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

define(['jquery', 'core/config', 'core/log'], function($, config, log) {
    "use strict";

    // Global variable for keepalive interval.
    var keepaliveInterval = null;

    /**
     * Function to tell the server to keep the session alive.
     */
    function doKeepAlive() {

        // Keep session alive by AJAX.
        // We know about the benefits of the core/ajax module (https://docs.moodle.org/dev/AJAX),
        // but for this very lightweight request we only use a simple jQuery AJAX call.
        $.ajax({
            url: config.wwwroot + '/mod/crucible/session_keepalive.php',
            dataType: 'json',
            type: 'POST',
            data: {
                'sesskey': config.sesskey,
                'time': $.now()
            },
            headers: {
                'Cache-Control': 'no-cache',
                'Expires': '-1'
            },
            // This section exists for understanding the code, but it is commented because it does nothing.
            success: function() {
            },
            error: function(request) {
                console.log("crucible keepalive failed");
                log.debug('moodle-mod_crucible-keepalive: ' . request);
                // The AJAX call returned 403, we have to assume that the session was terminated and can't be kept alive anymore.
                if (request.status == 403) {
                    // Stop doing any more requests.
                    clearInterval(keepaliveInterval);
                }
            }
        });
    }

    return {
        init: function(params) {
            // Initialize continuous keepalive check.
            if (params.keepaliveinterval !== null && params.keepaliveinterval > 0) {
                keepaliveInterval = setInterval(doKeepAlive, params.keepaliveinterval * 1000 * 60);
            }
        }
    };
});
