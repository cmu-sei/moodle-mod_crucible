define(['jquery', 'core/config'], function($, config) {

    return {
        init: function() {
            var buttons = document.querySelectorAll('.extend-event-btn');
            buttons.forEach(function(button) {
                if (button.dataset.bound) {
                    return;
                }
                button.dataset.bound = '1';

                button.onclick = function() {
                    var eventid = button.getAttribute('data-eventid');
                    var cmid = button.getAttribute('data-cmid');
                    var sesskey = button.getAttribute('data-sesskey');
                    var hoursInput = document.getElementById('extend-hours-' + eventid);
                    var minutesInput = document.getElementById('extend-minutes-' + eventid);
                    var hours = hoursInput ? parseFloat(hoursInput.value) : 0;
                    var minutes = minutesInput ? parseFloat(minutesInput.value) : 0;
                    var duration = Math.round((hours || 0) * 60) + Math.round(minutes || 0);
                    if (duration < 1) {
                        duration = 60;
                    }

                    button.disabled = true;
                    button.textContent = 'Extending...';

                    $.ajax({
                        url: config.wwwroot + '/mod/crucible/extendevent.php',
                        type: 'POST',
                        data: {
                            'sesskey': sesskey,
                            'id': eventid,
                            'duration': duration,
                            'cmid': cmid
                        },
                        success: function() {
                            button.textContent = 'Extended!';
                            setTimeout(function() {
                                window.location.replace(window.location.href);
                            }, 1000);
                        },
                        error: function() {
                            button.disabled = false;
                            button.textContent = 'Extend Event';
                            alert('Failed to extend event.');
                        }
                    });
                };
            });
        }
    };
});
