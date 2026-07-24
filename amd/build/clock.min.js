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

define(['jquery', 'core/config', 'core/log', 'core/modal_save_cancel', 'core/modal_events'],
    function($, config, log, ModalSaveCancel, ModalEvents) {

    var eventid;

    return {

        init: function(endtime, id) {

            eventid = id;

            var button = document.getElementById('extend-event');
            if (button) {
                button.setAttribute('data-original-text', button.innerHTML);
                button.onclick = function() {
                    open_extend_modal();
                };
                console.log('set event for extend-event button');
            }

            setInterval(function() {
                var timenow = Math.round(new Date().getTime() / 1000);
                var remaining = endtime - timenow;
                if (remaining <= 0) {
                    var timer = document.getElementById('timer');
                    if (timer) {
                        timer.innerHTML = "Your time has expired";
                        timer.className = "alert alert-danger d-inline-block mb-0";
                    }
                }
            }, 1000);
        },

        countdown: function(endtime) {

            setInterval(function() {
                var timenow = Math.round(new Date().getTime() / 1000);
                var remaining = endtime - timenow;
                if (remaining <= 0) {
                    return;
                }

                var days = Math.floor(remaining / (60 * 60 * 24));
                var hours = Math.floor(remaining % (60 * 60 * 24) / (60 * 60));
                var minutes = Math.floor(remaining % (60 * 60) / 60);
                var seconds = Math.floor(remaining % 60);

                var timer = document.getElementById('timer');
                if (timer) {
                    if (remaining < 300) {
                        timer.className = "alert alert-danger d-inline-block mb-0";
                    } else if (remaining < 1800) {
                        timer.className = "alert alert-warning d-inline-block mb-0";
                    } else {
                        timer.className = "alert alert-success d-inline-block mb-0";
                    }

                    timer.innerHTML = "Timer: " + days + "d " +
                        hours.toString().padStart(2, '0') + "h:" +
                        minutes.toString().padStart(2, '0') + "m:" +
                        seconds.toString().padStart(2, '0') + "s";
                }
            }, 1000);
        },

        countup: function(starttime) {

            setInterval(function() {
                var timenow = Math.round(new Date().getTime() / 1000);
                var running = timenow - starttime;

                var days = Math.floor(running / (60 * 60 * 24));
                var hours = Math.floor(running % (60 * 60 * 24) / (60 * 60));
                var minutes = Math.floor(running % (60 * 60) / 60);
                var seconds = Math.floor(running % 60);

                var timer = document.getElementById('timer');
                if (timer) {
                    timer.className = "alert alert-success d-inline-block mb-0";
                    timer.innerHTML = "Timer: " + days + "d " +
                        hours.toString().padStart(2, '0') + "h:" +
                        minutes.toString().padStart(2, '0') + "m:" +
                        seconds.toString().padStart(2, '0') + "s";
                }
            }, 1000);
        },
    };

    function open_extend_modal() {
        var modalContent = document.getElementById('extend-modal-content');
        if (!modalContent) {
            // No modal available (e.g. student view); fall back to legacy behavior.
            extend_event(null);
            return;
        }

        ModalSaveCancel.create({
            title: 'Extend Event',
            body: $('#extend-modal-content').html()
        }).then(function(modal) {
            modal.setSaveButtonText('Extend');

            var intervalInput = modal.getRoot().find('#extend-interval-input');
            intervalInput.on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });

            modal.getRoot().on(ModalEvents.save, function(e) {
                var input = intervalInput[0];
                if (input && !input.checkValidity()) {
                    e.preventDefault();
                    input.reportValidity();
                    return;
                }
                extend_event(parseInt(intervalInput.val(), 10));
            });

            modal.show();
            return modal;
        });
    }

    function extend_event(minutes) {
        var button = document.getElementById('extend-event');
        if (button) {
            button.disabled = true;
            button.innerHTML = 'Extending...';
        }

        var payload = {
            'sesskey': config.sesskey,
            'id': eventid
        };
        if (minutes) {
            payload.extendinterval = minutes;
        }

        $.ajax({
            url: config.wwwroot + '/mod/crucible/extendevent.php',
            type: 'POST',
            data: payload,
            headers: {
                'Cache-Control': 'no-cache',
                'Expires': '-1'
            },
            success: function(result) {
                console.log('extended lab');
                console.log(result);
                if (result.message === 'success') {
                    window.location.replace(window.location.href);
                } else {
                    alert('Extend failed: ' + (result.message || 'Unknown error'));
                    if (button) {
                        button.disabled = false;
                        button.innerHTML = button.getAttribute('data-original-text');
                    }
                }
            },
            error: function(request) {
                console.log("extend-event request failed");
                var errorMsg = 'Failed to extend lab. Please try again.';
                if (request.responseJSON && request.responseJSON.message) {
                    errorMsg = request.responseJSON.message;
                }
                alert(errorMsg);
                console.log(request);
                log.debug('moodle-mod_crucible-extend-event: ' . request);
                if (button) {
                    button.disabled = false;
                    button.innerHTML = button.getAttribute('data-original-text');
                }
            }
        });

    }
});
