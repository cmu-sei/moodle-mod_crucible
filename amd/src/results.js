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
        init: function(token, session, steamfitter_api) {

            console.log('session id ' + session);

            access_token = token;
            session_id = session;
            steamfitter_api_url = steamfitter_api;

            timeout = setInterval(get_results(), 5000);

            // TODO set event for each row in the table
            var id = '9949b988-1da1-42e5-8b95-4640b76cb505';
            var button = document.getElementById(id);
            if (button.innerHTML === "Execute") {
                button.onclick=function(){exec_task(id)};
            }

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
                console.log(response);
                $.each(response, function(index, value) {
                    console.log('index ' + index);
                    console.log('value ' + value);
                });
            },
            error: function(response) {
                    if (response.status == '401') {
                    console.log('permission error, check token');
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
                console.log(response);
                $.each(response, function(index, value) {
                    console.log('index ' + index);
                    console.log('value ' + value);
                });
            },
            error: function(response) {
                    if (response.status == '401') {
                    console.log('permission error, check token');
                    clearTimeout(timeout);
                }
            }
        });
 
   }

});
