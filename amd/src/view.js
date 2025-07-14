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
    var access_token;
    var lab_status;
    var event_id;
    var view_id;
    var alloy_api_url;
    var vm_app_url;
    var player_app_url;

    return {
        init: function() {
            const config = window.CrucibleConfig || {};
        
            access_token = config.token;
            lab_status = config.state;
            event_id = config.event;
            view_id = config.view;
            alloy_api_url = config.alloy_api_url;
            vm_app_url = config.vm_app_url;
            player_app_url = config.player_app_url;        

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

    function check_status() {

        if (event_id) {
            console.log('event id ' + event_id);
            $.ajax({
                url: alloy_api_url + '/events/' + event_id + '?t=' + new Date().getTime(),
                type: 'GET',
                contentType: 'application/json',
                dataType: 'json',
                beforeSend : function(xhr) {
                    xhr.setRequestHeader('Authorization', 'Bearer ' + access_token);
                },
                success: function(response) {
                    $.each(response, function(index, value) {
                        if (index == 'viewId') {
                            if (value) {
                                view_id = value;
                                console.log('view_id ' + view_id);
                             }
                        }
                        if (index == 'status') {
                            console.log('status ' + lab_status);
                            if (value == 'Active') {
                                //show_active();
                                clearTimeout(timeout);

                                if ((lab_status != 'Active') && (value == 'Active')) {
                                    console.log("reloading");
                                    window.location.replace(window.location.href);
                                }
                            }
                            if ((value == 'Creating') || (value == 'Planning') || (value == 'Applying') || (value == 'Ending')) {
                                show_wait();
                            }
                            if (value == 'Ended') {
                                //show_ended();
                                //clearTimeout(timeout);
                                window.location.replace(window.location.href);
                            }
                            if (value == 'Failed') {
                                show_failed();
                                clearTimeout(timeout);
                            }
                            lab_status = value;

                        }
                        // TODO move this into a script that checks task
                        // results and handles clicking on execution button
                        if (index == 'scenarioid') {
                            console.log('scenario id ' + value);
                        }
                    });
                },
                error: function(response) {
                    if (response.status == '401') {
                        console.log('permission error, check token');
                        clearTimeout(timeout);
                        window.location.replace(window.location.href);
                    }
                }
            });
        } else {
            show_ended();
        }
    }

    function show_wait() {
        editStyle('launch_button', 'display', 'none');
        editStyle('end_button', 'display', 'none');
        editStyle('wait', 'display', 'block');
        editStyle('vm_or_link', 'display', 'none');
        editStyle('failed', 'display', 'none');
        editStyle('crucible-container', 'display', 'none');
        editStyle('enable-fullscreen', 'display', 'none');
        editStyle('enable-fullscreen', 'display', 'none');
        editStyle('invite_button', 'display', 'none');
        editStyle('return-button', 'display', 'block');
        editStyle('join-form', 'display', 'block');
    }

    function show_ended() {
        editStyle('launch_button', 'display', 'block');
        editStyle('end_button', 'display', 'none');
        editStyle('wait', 'display', 'none');
        editStyle('vm_or_link', 'display', 'none');
        editStyle('failed', 'display', 'none');
        editStyle('timerdiv', 'display', 'none');
        editStyle('crucible-container', 'display', 'none');
        editStyle('enable-fullscreen', 'display', 'none');
        editStyle('invite_button', 'display', 'none');
        editStyle('return-button', 'display', 'block');
        editStyle('join-form', 'display', 'block');
    }

    function show_failed() {
        editStyle('launch_button', 'display', 'none');
        editStyle('end_button', 'display', 'none');
        editStyle('wait', 'display', 'none');
        editStyle('vm_or_link', 'display', 'none');
        editStyle('failed', 'display', 'block');
        editStyle('crucible-container', 'display', 'none');
        editStyle('enable-fullscreen', 'display', 'none');
        editStyle('invite_button', 'display', 'none');
        editStyle('return-button', 'display', 'none');
        editStyle('join-form', 'display', 'none');
    }

    function show_active() {
        editStyle('launch_button', 'display', 'none');
        editStyle('end_button', 'display', 'inline');
        editStyle('event', 'value', event_id);
        editStyle('wait', 'display', 'none');
        editStyle('failed', 'display', 'none');
        editStyle('timerdiv', 'display', 'inline');
        editStyle('crucible-container', 'display', 'block');
        editStyle('enable-fullscreen', 'display', 'inline');
        editStyle('invite_button', 'display', 'block');
        editStyle('return-button', 'display', 'block');
        editStyle('join-form', 'display', 'block');

        var x = document.getElementById('vm_or_link');
        if (x) {
            if (x.getAttribute('src') !== null) {
                x.setAttribute('src', vm_app_url + '/views/' + view_id);
            }
            if (x.getAttribute('href')) {
                x.setAttribute('href', player_app_url + '/view/' + view_id);
            }
            x.style.display = 'block';
        }
   }

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
            if (lab_status == 'Ended') {
                console.log('lab has ended');
            }
        }, 5000);
    }
});

function copy_link(link) {
    // navigator clipboard api needs a secure context (https)
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(link);
    } else {
        let textArea = document.createElement("textarea");
        textArea.value = link;
        // make the textarea out of viewport
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        return new Promise((res, rej) => {
            document.execCommand('copy') ? res() : rej();
            textArea.remove();
        });
    }
}
