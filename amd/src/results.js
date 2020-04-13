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
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release
and unlimited distribution.  Please see Copyright notice for non-US Government
use and distribution.
This Software includes and/or makes use of the following Third-Party Software
subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas
DM20-0196
 */

define(['jquery'], function($) {

    var access_token;
    var session_id;
    var steamfitter_api_url;
    var timeout;

    return {
        //init: function(token, session, steamfitter_api) {
        init: function(info) {

            console.log('session id ' + info.session);

            access_token = info.token;
            session_id = info.session;
            steamfitter_api_url = info.steamfitter_api;

            get_results();

            timeout = setInterval(function() {
                get_results();
            }, 5000);

            var tasks = document.getElementsByClassName('exec-task');
            $.each(tasks, function(index, value) {
                var id = value.id;
                var button = document.getElementById(id);
                if (button) {
                    // TODO replace this check
                    if (button.innerHTML === "Run Task") {
                        button.onclick = function() {
                            exec_task(id);
                        };
                    }
                }
                console.log('set event for button ' + id);
            });
        }
    };


    function get_results() {

        $.ajax({
            url: steamfitter_api_url + '/sessions/' + session_id + '/dispatchtaskresults',
            type: 'GET',
            contentType: 'application/json',
            dataType: 'json',
            beforeSend : function(xhr) {
                xhr.setRequestHeader('Authorization', 'Bearer ' + access_token);
            },
            success: function(response) {

                // this will ensure the list is time sorted so that we get
                // the most recent status displayed
                response.sort(function(a, b) {
                    return (a.statusDate > b.statusDate) ? 1 : -1;
                });

                $.each(response, function(index, value) {
                    var result = document.getElementById('result-' + value.dispatchTaskId);
                    if (result) {
                        result.innerHTML = value.status;
                    }
                    // TODO push score to moodle for saving in the db
                });
            },
            error: function(response) {
                if (response.status == '401') {
                    console.log('permission error, check token, reload page');
                    alert('permission error, check token, reload page');
                    clearTimeout(timeout);
                } else {
                    console.log(response);
                    clearTimeout(timeout);
                }
            }
        });
    }

    function exec_task(id) {
        console.log('exec task for ' + id);

        $.ajax({
            url: steamfitter_api_url + '/dispatchtasks/' + id + '/execute',
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            beforeSend : function(xhr) {
                xhr.setRequestHeader('Authorization', 'Bearer ' + access_token);
            },
            success: function(response) {
                $.each(response, function(index, value) {
                    console.log(value.statusDate + ' task ' + value.dispatchTaskId + ' status ' + value.status);
                    var result = document.getElementById('result-' + value.dispatchTaskId);
                    if (result) {
                        result.innerHTML = value.status;
                    }
                });
            },
            error: function(response) {
                if (response.status == '401') {
                    console.log('permission error, check token, reload page');
                    alert('permission error, check token, reload page');
                } else if (response.status == '400') {
                    console.log(response.responseJSON);
                    alert('Run Task command failed ' + response.responseJSON.detail);
                } else if (response.status == '500') {
                    console.log(response.responseJSON);
                    alert('Run Task command failed ' + response.responseJSON.detail);
                }
            }
        });

   }

});
