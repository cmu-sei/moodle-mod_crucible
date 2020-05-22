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

define(['jquery', 'core/config', 'core/log'], function($, config, log) {

    var scenario_id;
    var timeout;
    var attempt;
    var cmid;

    return {
        init: function(info) {

            console.log('scenario id ' + info.scenario);

            scenario_id = info.scenario;
            attempt = info.attempt;
            cmid = info.cmid;

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
                            exec_task_mdl(id);
                        };
                        console.log('set event for button ' + id);
                    }
                }
            });
        }
    };

    function get_results() {
        $.ajax({
            url: config.wwwroot + '/mod/crucible/getresults.php',
            dataType: 'json',
            type: 'POST',
            data: {
                'sesskey': config.sesskey,
                'time': $.now(),
                'id': scenario_id,
                'cmid': cmid
            },
            headers: {
                'Cache-Control': 'no-cache',
                'Expires': '-1'
            },
            success: function(response) {
                console.log(response);
                if (response.parsed) {
                    response.parsed.sort(function(a, b) {
                        return (a.statusDate > b.statusDate) ? 1 : -1;
                    });
                }
                $.each(response.parsed, function(index, value) {
                    var result = document.getElementById('result-' + value.taskId);
                    if (result) {
                        result.innerHTML = value.status;
                    }
                    var score = document.getElementById('score-' + value.taskId);
                    if (score) {
                        score.innerHTML = value.score;
                    }
                });
            },
            error: function(response) {
                console.log('error');
                console.log(response);
            }
        });
    }

    function exec_task_mdl(id) {
        console.log('exec task for ' + id);

        $.ajax({
            url: config.wwwroot + '/mod/crucible/runtask.php',
            dataType: 'json',
            type: 'POST',
            data: {
                'sesskey': config.sesskey,
                'time': $.now(),
                'id': id,
                'a': attempt,
                'cmid': cmid
            },
            headers: {
                'Cache-Control': 'no-cache',
                'Expires': '-1'
            },
            success: function(result) {
                console.log(result);
                var score = document.getElementById('attempt-score');
                if (score) {
                    score.innerHTML = result.score;
                }
            },
            error: function(request) {
                console.log("crucible task failed");
                console.log(request);
                log.debug('moodle-mod_crucible-runtask: ' . request);
            }
        });
    }
});
