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

define(['jquery'], function($) {
    var timeout;
    var lab_status;
    var event_id;
    var view_id;
    var status_url;
    var cmid;
    var sesskey;
    var vm_app_url;
    var player_app_url;
    var waitDots = 0;
    var currentWaitStatus = null;
    var defaultWaitText = 'Please wait, system processing…';
    var reloading = false;


    return {
        init: function(config) {
            lab_status = config.state;
            event_id = config.event;
            view_id = config.view;
            status_url = config.status_url;
            cmid = config.cmid;
            sesskey = config.sesskey;
            vm_app_url = config.vm_app_url;
            player_app_url = config.player_app_url;

            var label = document.getElementById('wait-text');
            if (label) {
                defaultWaitText = label.textContent;
            }

            if (lab_status == 'Active') {
                show_active();
            } else if (lab_status == '') {
                show_ended();
            } else {
                show_wait();
            }

            var button = document.getElementById('enable-fullscreen');
            if (button) {
                button.onclick = function() {
                    var frame = document.getElementById('vm_or_link');
                    frame.requestFullscreen();
                };
            }

            run_loop();

        }
    };

    /**
     *
     */
    function check_status() {

        if (event_id) {
            console.log('event id ' + event_id);
            $.ajax({
                url: status_url,
                type: 'GET',
                dataType: 'json',
                data: {
                    cmid: cmid,
                    eventid: event_id,
                    sesskey: sesskey
                },
                success: function(response) {
                    handle_status(response);
                },
                error: function(response, textStatus, errorThrown) {
                    console.error('event status request failed', response.status, textStatus, errorThrown);
                }
            });
        } else {
            show_ended();
        }
    }

    function handle_status(response) {
        var nextStatus = response.status;
        var nextViewId = response.viewId;
        var viewChanged = nextViewId && nextViewId !== view_id;

        if (nextViewId) {
            view_id = nextViewId;
            console.log('view_id ' + view_id);
        }

        console.log('status ' + nextStatus);
        if (nextStatus == 'Active' && (lab_status != 'Active' || viewChanged)) {
            clear_wait_label();
            console.log('reloading');
            reload_page();
            return;
        }

        if ((nextStatus == 'Creating') || (nextStatus == 'Planning') ||
                (nextStatus == 'Applying') || (nextStatus == 'Ending')) {
            show_wait();
            update_wait_label(nextStatus);
        } else if (nextStatus == 'Ended') {
            show_ended();
            clear_wait_label();
            reload_page();
            return;
        } else if (nextStatus == 'Failed') {
            clear_wait_label();
            show_failed();
        }

        lab_status = nextStatus;
    }

    /**
     *
     */
    function show_wait() {
        editStyle('launch_button', 'display', 'none');
        editStyle('end_button', 'display', 'none');
        editStyle('timerdiv', 'display', 'none');
        editStyle('wait', 'display', 'block');
        editStyle('vm_or_link', 'display', 'none');
        editStyle('crucible-workspace-section', 'display', 'none');
        editStyle('failed', 'display', 'none');
        editStyle('crucible-container', 'display', 'none');
        editStyle('enable-fullscreen', 'display', 'none');
        editStyle('enable-fullscreen', 'display', 'none');
        editStyle('extend-event', 'display', 'none');
        editStyle('invite_button', 'display', 'none');
        editStyle('return-button', 'display', 'block');
        editStyle('join-form', 'display', 'block');
    }

    /**
     *
     */
    function show_ended() {
        editStyle('launch_button', 'display', 'inline-block');
        editStyle('end_button', 'display', 'none');
        editStyle('wait', 'display', 'none');
        editStyle('vm_or_link', 'display', 'none');
        editStyle('crucible-workspace-section', 'display', 'none');
        editStyle('failed', 'display', 'none');
        editStyle('timerdiv', 'display', 'none');
        editStyle('crucible-container', 'display', 'none');
        editStyle('enable-fullscreen', 'display', 'none');
        editStyle('extend-event', 'display', 'none');
        editStyle('invite_button', 'display', 'none');
        editStyle('return-button', 'display', 'block');
        editStyle('join-form', 'display', 'block');
    }

    /**
     *
     */
    function show_failed() {
        editStyle('launch_button', 'display', 'none');
        editStyle('end_button', 'display', 'none');
        editStyle('wait', 'display', 'none');
        editStyle('vm_or_link', 'display', 'none');
        editStyle('crucible-workspace-section', 'display', 'none');
        editStyle('failed', 'display', 'block');
        editStyle('crucible-container', 'display', 'none');
        editStyle('enable-fullscreen', 'display', 'none');
        editStyle('extend-event', 'display', 'none');
        editStyle('copy_invite', 'display', 'none');
        editStyle('return-button', 'display', 'none');
        editStyle('join-form', 'display', 'none');
    }

    /**
     *
     */
    function show_active() {
        editStyle('launch_button', 'display', 'none');
        editStyle('timerdiv', 'display', 'inline-flex');
        editStyle('end_button', 'display', 'inline');
        editStyle('event', 'value', event_id);
        editStyle('wait', 'display', 'none');
        editStyle('failed', 'display', 'none');
        editStyle('crucible-workspace-section', 'display', 'block');
        editStyle('crucible-container', 'display', 'block');
        editStyle('enable-fullscreen', 'display', 'inline');
        editStyle('extend-event', 'display', 'inline-block');
        editStyle('copy_invite', 'display', 'inline');
        editStyle('return-button', 'display', 'block');
        editStyle('join-form', 'display', 'block');

        var x = document.getElementById('vm_or_link');
        if (x) {
            var display = 'block';
            if (x.getAttribute('src') !== null) {
                x.setAttribute('src', vm_app_url + '/views/' + view_id);
            }
            if (x.getAttribute('href')) {
                x.setAttribute('href', player_app_url + '/view/' + view_id);
                // Hide fullscreen button in link mode (not embed mode).
                editStyle('enable-fullscreen', 'display', 'none');
                display = 'inline-block';
            }
            x.style.display = display;
        }
   }

   /**
    *
    * @param elementId
    * @param styleName
    * @param styleValue
    */
   function editStyle(elementId, styleName, styleValue) {
       var x = document.getElementById(elementId);

       if (x) {
           x.style[styleName] = styleValue;
       }
   }

    function run_loop() {
        timeout = setTimeout(function() {
            check_status();
            run_loop();
        }, 5000);
    }

    function reload_page() {
        if (reloading) {
            return;
        }

        reloading = true;
        clearTimeout(timeout);
        window.location.replace(window.location.href);
    }

    function update_wait_label(status) {
        var label = document.getElementById('wait-text');
        if (!label) {
            return;
        }

        if (!status) {
            clear_wait_label();
            return;
        }

        if (currentWaitStatus === status) {
            waitDots++;
        } else {
            currentWaitStatus = status;
            waitDots = 1;
        }

        var maxDots = 10;
        if (waitDots > maxDots) {
            waitDots = maxDots;
        }

        var dots = new Array(waitDots + 1).join('.');

        var displayStatus = status.charAt(0).toLowerCase() + status.slice(1);

        label.textContent = 'Please wait, ' + displayStatus + dots;
    }

    function clear_wait_label() {
        var label = document.getElementById('wait-text');
        if (label) {
            label.textContent = defaultWaitText;
        }
        currentWaitStatus = null;
        waitDots = 0;
    }


});
