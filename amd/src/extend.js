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
                    var durationInput = document.getElementById('extend-duration-' + eventid);
                    var hours = parseFloat(durationInput.value) || 1;
                    var duration = Math.round(hours * 60);

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
