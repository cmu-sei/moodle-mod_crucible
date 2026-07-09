define(['jquery', 'core/modal_save_cancel', 'core/modal_events'], function($, ModalSaveCancel, ModalEvents) {
    return {
        init: function(cmid, sesskey) {
            var selectedUsers = function() {
                return Array.prototype.slice.call(document.querySelectorAll('.user-checkbox:checked'))
                    .map(function(cb) {
                        return cb.value;
                    });
            };

            var updateButtons = function() {
                var count = selectedUsers().length;
                var extendBtn = document.getElementById('extend-selected-btn');
                var scheduleBtn = document.getElementById('schedule-selected-btn');
                var endBtn = document.getElementById('end-selected-btn');
                var activeCount = 0;
                var scheduleCount = 0;

                document.querySelectorAll('.user-checkbox:checked').forEach(function(cb) {
                    var row = cb.closest('tr');
                    var status = row ? (row.getAttribute('data-status') || '').trim().toLowerCase() : '';
                    if (status === 'active') {
                        activeCount++;
                    } else {
                        scheduleCount++;
                    }
                });

                if (extendBtn) {
                    extendBtn.disabled = activeCount === 0;
                    extendBtn.textContent = activeCount > 0 ? 'Extend Selected (' + activeCount + ')' : 'Extend Selected';
                }
                if (scheduleBtn) {
                    scheduleBtn.disabled = scheduleCount === 0;
                    scheduleBtn.textContent = scheduleCount > 0 ? 'Schedule Selected (' + scheduleCount + ')' : 'Schedule Selected';
                }
                if (endBtn) {
                    endBtn.disabled = activeCount === 0;
                    endBtn.textContent = activeCount > 0 ? 'End Selected (' + activeCount + ')' : 'End Selected';
                }
            };

            var selectAllBtn = document.getElementById('select-all-btn');
            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', function() {
                    document.querySelectorAll('.user-checkbox:not(:disabled)').forEach(function(cb) {
                        cb.checked = true;
                    });
                    updateButtons();
                });
            }

            var deselectAllBtn = document.getElementById('deselect-all-btn');
            if (deselectAllBtn) {
                deselectAllBtn.addEventListener('click', function() {
                    document.querySelectorAll('.user-checkbox').forEach(function(cb) {
                        cb.checked = false;
                    });
                    updateButtons();
                });
            }

            var selectAllCheckbox = document.getElementById('select-all-checkbox');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    document.querySelectorAll('.user-checkbox:not(:disabled)').forEach(function(cb) {
                        cb.checked = selectAllCheckbox.checked;
                    });
                    updateButtons();
                });
            }

            document.querySelectorAll('.user-checkbox').forEach(function(cb) {
                cb.addEventListener('change', updateButtons);
            });

            var usersByStatus = function(expectActive) {
                return Array.prototype.slice.call(document.querySelectorAll('.user-checkbox:checked'))
                    .filter(function(cb) {
                        var row = cb.closest('tr');
                        var status = row ? (row.getAttribute('data-status') || '').trim().toLowerCase() : '';
                        return expectActive ? status === 'active' : status !== 'active';
                    })
                    .map(function(cb) {
                        return cb.value;
                    });
            };

            var extendBtn = document.getElementById('extend-selected-btn');
            if (extendBtn) {
                extendBtn.addEventListener('click', function() {
                    var users = usersByStatus(true);
                    if (users.length === 0) {
                        return;
                    }

                    ModalSaveCancel.create({
                        title: 'Extend Selected (' + users.length + ')',
                        body: '<div class="form-group">' +
                            '<label for="crucible-extend-hours">Hours to add</label>' +
                            '<input type="number" id="crucible-extend-hours" class="form-control" ' +
                            'value="1" min="0" max="168" step="1" style="max-width: 120px;">' +
                            '</div>' +
                            '<div class="form-group">' +
                            '<label for="crucible-extend-minutes">Minutes to add</label>' +
                            '<input type="number" id="crucible-extend-minutes" class="form-control" ' +
                            'value="0" min="0" max="59" step="1" style="max-width: 120px;">' +
                            '</div>'
                    }).then(function(modal) {
                        modal.setSaveButtonText('Extend');

                        modal.getRoot().on(ModalEvents.save, function() {
                            var hours = modal.getRoot().find('#crucible-extend-hours').val();
                            var additionalMinutes = modal.getRoot().find('#crucible-extend-minutes').val();
                            var minutes = Math.round((parseFloat(hours) || 0) * 60)
                                + Math.round(parseFloat(additionalMinutes) || 0);
                            if (minutes < 1) {
                                minutes = 60;
                            }
                            document.getElementById('extend-userids').value = users.join(',');
                            document.getElementById('extend-duration').value = minutes;
                            document.getElementById('extend-form').submit();
                        });

                        modal.show();
                        return modal;
                    });
                });
            }

            var pad2 = function(value) {
                return String(value).padStart(2, '0');
            };

            var dateInputValue = function(date) {
                return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
            };

            var buildOptions = function(values, selected) {
                return values.map(function(item) {
                    var value = String(item.value);
                    var selectedAttr = value === String(selected) ? ' selected' : '';
                    return '<option value="' + value + '"' + selectedAttr + '>' + item.label + '</option>';
                }).join('');
            };

            var scheduleModalBody = function(defaultTime, timezone) {
                var hour24 = defaultTime.getHours();
                var hour12 = hour24 % 12;
                var minute = Math.ceil(defaultTime.getMinutes() / 5) * 5;
                if (minute === 60) {
                    minute = 0;
                    defaultTime.setHours(hour24 + 1);
                    hour24 = defaultTime.getHours();
                    hour12 = hour24 % 12;
                }
                hour12 = hour12 === 0 ? 12 : hour12;

                var hourOptions = [];
                for (var h = 1; h <= 12; h++) {
                    hourOptions.push({value: h, label: String(h)});
                }

                var minuteOptions = [];
                for (var m = 0; m < 60; m += 5) {
                    minuteOptions.push({value: m, label: pad2(m)});
                }

                return '<div class="form-group">' +
                    '<label for="crucible-schedule-date">Date</label>' +
                    '<input type="date" id="crucible-schedule-date" class="form-control" ' +
                    'value="' + dateInputValue(defaultTime) + '" required>' +
                    '</div>' +
                    '<div class="form-group">' +
                    '<label>Time</label>' +
                    '<div class="form-inline">' +
                    '<select id="crucible-schedule-hour" class="custom-select mr-2 mb-2" ' +
                    'aria-label="Hour">' + buildOptions(hourOptions, hour12) + '</select>' +
                    '<select id="crucible-schedule-minute" class="custom-select mr-2 mb-2" ' +
                    'aria-label="Minute">' + buildOptions(minuteOptions, minute) + '</select>' +
                    '<select id="crucible-schedule-ampm" class="custom-select mb-2" ' +
                    'aria-label="AM or PM">' +
                    buildOptions([{value: 'AM', label: 'AM'}, {value: 'PM', label: 'PM'}], hour24 >= 12 ? 'PM' : 'AM') +
                    '</select>' +
                    '</div>' +
                    '<small class="form-text text-muted">Your timezone: ' +
                    (timezone || 'browser local time') +
                    '</small>' +
                    '</div>';
            };

            var scheduleBtn = document.getElementById('schedule-selected-btn');
            if (scheduleBtn) {
                scheduleBtn.addEventListener('click', function() {
                    var users = usersByStatus(false);
                    if (users.length === 0) {
                        return;
                    }

                    var oneHourFromNow = new Date();
                    oneHourFromNow.setHours(oneHourFromNow.getHours() + 1);
                    var timezone = '';
                    if (window.Intl && Intl.DateTimeFormat) {
                        timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
                    }

                    ModalSaveCancel.create({
                        title: 'Schedule Selected (' + users.length + ')',
                        body: scheduleModalBody(oneHourFromNow, timezone)
                    }).then(function(modal) {
                        modal.setSaveButtonText('Schedule');

                        modal.getRoot().on(ModalEvents.save, function() {
                            var date = modal.getRoot().find('#crucible-schedule-date').val();
                            var hour = parseInt(modal.getRoot().find('#crucible-schedule-hour').val(), 10);
                            var minute = parseInt(modal.getRoot().find('#crucible-schedule-minute').val(), 10);
                            var ampm = modal.getRoot().find('#crucible-schedule-ampm').val();
                            if (!date || Number.isNaN(hour) || Number.isNaN(minute)) {
                                return;
                            }

                            var parts = date.split('-').map(function(part) {
                                return parseInt(part, 10);
                            });
                            if (parts.length !== 3 || parts.some(Number.isNaN)) {
                                return;
                            }

                            if (ampm === 'PM' && hour !== 12) {
                                hour += 12;
                            } else if (ampm === 'AM' && hour === 12) {
                                hour = 0;
                            }

                            var scheduledFor = new Date(parts[0], parts[1] - 1, parts[2], hour, minute, 0, 0);
                            document.getElementById('schedule-userids').value = users.join(',');
                            document.getElementById('schedule-timezone').value = timezone;
                            document.getElementById('schedule-timestamp').value =
                                Math.floor(scheduledFor.getTime() / 1000);
                            document.getElementById('schedule-form').submit();
                        });

                        modal.show();
                        return modal;
                    });
                });
            }

            var endBtn = document.getElementById('end-selected-btn');
            if (endBtn) {
                endBtn.addEventListener('click', function() {
                    var users = usersByStatus(true);
                    if (users.length === 0) {
                        return;
                    }
                    if (!window.confirm('End labs for ' + users.length + ' selected users?')) {
                        return;
                    }
                    document.getElementById('end-userids').value = users.join(',');
                    document.getElementById('end-form').submit();
                });
            }

            updateButtons();
        }
    };
});
