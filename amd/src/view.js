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
    var implementation_id;
    var exercise_id;
    var alloy_api_url;
    var vm_app_url;
    var player_app_url;

    return {
        init: function(token, state, id, exercise, alloy_api, vm_app, player_app) {

            access_token = token;
            lab_status = state;
            implementation_id = id;
            exercise_id = exercise;
            alloy_api_url = alloy_api;
            vm_app_url = vm_app;
            player_app_url = player_app;

            if (lab_status == 'Active') {
                show_active();
            } else if (lab_status == '') {
                show_ended();
            } else {
                show_wait();
            }
            run_loop();

        }
    };

    function check_status() {

        if (implementation_id) {
            console.log('implementation id ' + implementation_id);
            $.ajax({
                url: alloy_api_url + '/implementations/' + implementation_id,
                type: 'GET',
                contentType: 'application/json',
                dataType: 'json',
                beforeSend : function(xhr) {
                    xhr.setRequestHeader('Authorization', 'Bearer ' + access_token);
                },
                success: function(response) {
                    $.each(response, function(index, value) {
                        if (index == 'exerciseId') {
                            if (value) {
                                exercise_id = value;
                                console.log('exercise_id ' + exercise_id);
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
                        if (index == 'sessionid') {
                            console.log('session id ' + value);
                        }
                    });
                },
                error: function(response) {
                    if (response.status == '401') {
                        console.log('permission error, check token');
                        clearTimeout(timeout);
                    }
                }
            });
        } else {
            show_ended();
        }
    }

    function show_wait() {
        var x = document.getElementById('launch_button');
        x.style.display = 'none';
        var x = document.getElementById('end_button');
        x.style.display = 'none';
        var x = document.getElementById('wait');
        x.style.display = 'block';
        var x = document.getElementById('vm_or_link');
        x.style.display = 'none';
        var x = document.getElementById('failed');
        x.style.display = 'none';
        var x = document.getElementById('crucible-container');
        x.style.display = 'none';
    }

    function show_ended() {
        var x = document.getElementById('launch_button');
        x.style.display = 'block';
        var x = document.getElementById('end_button');
        x.style.display = 'none';
        var x = document.getElementById('wait');
        x.style.display = 'none';
        var x = document.getElementById('vm_or_link');
        x.style.display = 'none';
        var x = document.getElementById('failed');
        x.style.display = 'none';
        var x = document.getElementById('timerdiv');
        if (x) {
            x.style.display = 'none';
        }
        var x = document.getElementById('crucible-container');
        x.style.display = 'none';
    }

    function show_failed() {
        var x = document.getElementById('launch_button');
        x.style.display = 'none';
        var x = document.getElementById('end_button');
        x.style.display = 'none';
        var x = document.getElementById('wait');
        x.style.display = 'none';
        var x = document.getElementById('vm_or_link');
        x.style.display = 'none';
        var x = document.getElementById('failed');
        x.style.display = 'block';
        var x = document.getElementById('crucible-container');
        x.style.display = 'none';
    }

    function show_active() {
        var x = document.getElementById('launch_button');
        x.style.display = 'none';
        var x = document.getElementById('end_button');
        x.style.display = 'block';
        var x = document.getElementById('implementation');
        x.style.value = implementation_id;
        var x = document.getElementById('wait');
        x.style.display = 'none';
        var x = document.getElementById('failed');
        x.style.display = 'none';
        var x = document.getElementById('vm_or_link');
        if (x.getAttribute('src') !== null) {
            x.setAttribute('src', vm_app_url + '/exercises/' + exercise_id);
        }
        if (x.getAttribute('href')) {
            x.setAttribute('href', player_app_url + '/exercise-player/' + exercise_id);
        }
        x.style.display = 'block';
        var x = document.getElementById('timerdiv');
        if (x) {
            x.style.display = 'block';
        }
        var x = document.getElementById('crucible-container');
        x.style.display = 'block';
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
