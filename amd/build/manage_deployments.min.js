define(['jquery', 'core/modal_save_cancel', 'core/modal_events', 'theme_boost/bootstrap/popover'],
    function($, ModalSaveCancel, ModalEvents, Popover) {
    const POLL_MS = 5000;

    return {
        init: function(cmid, sesskey) {
            const root = document.querySelector('.mod-crucible-manage-deployments');
            if (!root) {
                return;
            }

            // The schedule template is rendered by PHP when this page loads. Keep
            // its wall-clock value in the Moodle user timezone, but advance it by
            // the elapsed time when the modal opens so it remains one hour ahead.
            const scheduleTemplateLoadedAt = Date.now();
            const formatScheduleDateTime = (timestamp) => {
                const date = new Date(timestamp);
                const pad = (value) => String(value).padStart(2, '0');
                return date.getUTCFullYear() + '-' + pad(date.getUTCMonth() + 1) + '-'
                    + pad(date.getUTCDate()) + 'T' + pad(date.getUTCHours()) + ':'
                    + pad(date.getUTCMinutes());
            };
            const parseScheduleDateTime = (value) => {
                const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(value);
                if (!match) {
                    return null;
                }

                return Date.UTC(
                    Number(match[1]),
                    Number(match[2]) - 1,
                    Number(match[3]),
                    Number(match[4]),
                    Number(match[5])
                );
            };
            const freshScheduleDateTime = (value) => {
                const templateTime = parseScheduleDateTime(value);
                if (templateTime === null) {
                    return value;
                }
                return formatScheduleDateTime(templateTime + (Date.now() - scheduleTemplateLoadedAt));
            };
            const minimumScheduleDateTime = (value) => {
                const templateTime = parseScheduleDateTime(value);
                if (templateTime === null) {
                    return value;
                }

                const currentTime = templateTime - (60 * 60 * 1000)
                    + (Date.now() - scheduleTemplateLoadedAt);
                return formatScheduleDateTime(Math.ceil(currentTime / 60000) * 60000);
            };

            // Initialize Bootstrap popovers for help icons
            const initPopovers = () => {
                document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
                    // Dispose existing popover instance if present
                    const existingPopover = Popover.getInstance(el);
                    if (existingPopover) {
                        existingPopover.dispose();
                    }
                    // Create new popover instance
                    new Popover(el);
                });
            };
            initPopovers();

            let timer = null;
            let inactivePollCount = 0;
            const MAX_INACTIVE_POLLS = 6; // Stop after 6 polls (30 seconds) with no activity

            const rowStatus = (row) => (row.getAttribute('data-status') || '').trim().toLowerCase();

            const hasActiveDeploys = () => {
                const table = document.querySelector('.mod-crucible-users-table tbody');
                if (!table) {
                    return false;
                }
                const rows = table.querySelectorAll('tr');
                for (const row of rows) {
                    const status = rowStatus(row);
                    if (status === 'pending' || status === 'launched'
                        || status === 'in progress' || status === 'scheduled') {
                        return true;
                    }
                }
                return false;
            };

            const stop = () => {
                if (timer) {
                    window.clearTimeout(timer);
                    timer = null;
                }
            };

            const applyUpdate = (user) => {
                const safeId = String(user.userid).replace(/"/g, '\\"');
                const row = document.querySelector('tr[data-userid="' + safeId + '"]');
                if (!row) {
                    return;
                }

                row.setAttribute('data-status', user.status_class || 'none');

                const statusCell = row.querySelector('.cell-status');
                if (statusCell) {
                    if (user.tooltip_html) {
                        statusCell.innerHTML = user.tooltip_html;
                        // Re-initialize popover for new content
                        initPopovers();
                    } else {
                        statusCell.textContent = user.status_label || '';
                    }
                }
                const eventCell = row.querySelector('.cell-event');
                if (eventCell) {
                    eventCell.textContent = user.event_text || '─';
                }
                const schedCell = row.querySelector('.cell-scheduled');
                if (schedCell) {
                    schedCell.textContent = user.scheduled_text || '─';
                }
                const endTimeCell = row.querySelector('.cell-end-time');
                if (endTimeCell) {
                    endTimeCell.textContent = user.end_time_text || '─';
                }
                const actionsCell = row.querySelector('.cell-actions');
                if (actionsCell) {
                    actionsCell.innerHTML = user.action_html || '─';
                }
            };

            const updateNotification = (data) => {
                const note = document.getElementById('deploy-notification');
                if (!note) {
                    return;
                }
                if (!data.has_active) {
                    note.style.display = 'none';
                    return;
                }
                note.style.display = '';
                const ready = data.progress_summary ? data.progress_summary.ready : 0;
                const total = data.progress_summary ? data.progress_summary.total : 0;
                const readySpan = note.querySelector('.deploy-summary-ready');
                if (readySpan) {
                    readySpan.textContent = String(ready);
                }
                const totalSpan = note.querySelector('.deploy-summary-total');
                if (totalSpan) {
                    totalSpan.textContent = String(total);
                }
            };

            const updateBulkActionButtons = () => {
                const checkboxes = document.querySelectorAll('.user-checkbox:checked');
                const deployBtn = document.getElementById('deploy-selected-btn');
                const scheduleBtn = document.getElementById('schedule-selected-btn');
                const cancelBtn = document.getElementById('cancel-selected-btn');
                const endBtn = document.getElementById('end-selected-btn');
                const extendBtn = document.getElementById('extend-selected-btn');

                // Count by status
                let canDeploy = 0, canCancel = 0, canEnd = 0, canExtend = 0;

                checkboxes.forEach(cb => {
                    const row = cb.closest('tr');
                    if (row) {
                        const status = rowStatus(row);

                        // Can deploy/schedule: None, Finished, Abandoned, Overdue, Ready, Failed, Cancelled
                        if (status === 'none' || status === 'finished' || status === 'abandoned' ||
                            status === 'overdue' || status === 'ready' || status === 'failed' || status === 'cancelled') {
                            canDeploy++;
                        }
                        // Can cancel: Pending, Launched, Scheduled
                        if (status === 'pending' || status === 'launched' || status === 'scheduled') {
                            canCancel++;
                        }
                        // Can end: In Progress
                        if (status === 'in progress') {
                            canEnd++;
                            canExtend++;
                        }
                    }
                });

                if (deployBtn) {
                    deployBtn.disabled = canDeploy === 0;
                    deployBtn.textContent = canDeploy > 0 ?
                        'Deploy Selected (' + canDeploy + ')' :
                        'Deploy Selected Now';
                }

                if (scheduleBtn) {
                    scheduleBtn.disabled = canDeploy === 0;
                    scheduleBtn.textContent = canDeploy > 0 ?
                        'Schedule Selected (' + canDeploy + ')...' :
                        'Schedule Selected...';
                }

                if (cancelBtn) {
                    cancelBtn.disabled = canCancel === 0;
                    cancelBtn.textContent = canCancel > 0 ?
                        'Cancel Selected (' + canCancel + ')' :
                        'Cancel Selected';
                }

                if (endBtn) {
                    endBtn.disabled = canEnd === 0;
                    endBtn.textContent = canEnd > 0 ?
                        'End Selected (' + canEnd + ')' :
                        'End Selected';
                }

                if (extendBtn) {
                    extendBtn.disabled = canExtend === 0;
                    extendBtn.textContent = canExtend > 0 ?
                        'Extend Selected (' + canExtend + ')' :
                        'Extend Selected';
                }
            };

            const selectAllBtn = document.getElementById('select-all-btn');
            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', function() {
                    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = true);
                    updateBulkActionButtons();
                });
            }

            const deselectAllBtn = document.getElementById('deselect-all-btn');
            if (deselectAllBtn) {
                deselectAllBtn.addEventListener('click', function() {
                    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
                    updateBulkActionButtons();
                });
            }

            const selectAllCheckbox = document.getElementById('select-all-checkbox');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = this.checked);
                    updateBulkActionButtons();
                });
            }

            document.querySelectorAll('.user-checkbox').forEach(cb => {
                cb.addEventListener('change', updateBulkActionButtons);
            });

            const deploySelectedBtn = document.getElementById('deploy-selected-btn');
            if (deploySelectedBtn) {
                deploySelectedBtn.addEventListener('click', function() {
                    const selected = Array.from(document.querySelectorAll('.user-checkbox:checked'))
                        .map(cb => cb.value);

                    if (selected.length === 0) {
                        alert('No users selected');
                        return;
                    }

                    ModalSaveCancel.create({
                        title: 'Deploy Selected (' + selected.length + ')',
                        body: $('#deploy-modal-content').html()
                    }).then(function(modal) {
                        modal.setSaveButtonText('Deploy Now');

                        modal.getRoot().on(ModalEvents.save, function() {
                            $('#deploy-userids').val(selected.join(','));
                            $('#deploy-form').submit();
                        });

                        modal.show();
                        return modal;
                    });
                });
            }

            const scheduleSelectedBtn = document.getElementById('schedule-selected-btn');
            if (scheduleSelectedBtn) {
                scheduleSelectedBtn.addEventListener('click', function() {
                    const selected = Array.from(document.querySelectorAll('.user-checkbox:checked'))
                        .map(cb => cb.value);

                    if (selected.length === 0) {
                        alert('No users selected');
                        return;
                    }

                    ModalSaveCancel.create({
                        title: 'Schedule Selected (' + selected.length + ')',
                        body: $('#schedule-modal-content').html()
                    }).then(function(modal) {
                        modal.setSaveButtonText('Schedule');

                        const datetimeInput = modal.getRoot().find('#scheduledfor-input');
                        const templateDatetime = datetimeInput.val();
                        datetimeInput.attr('min', minimumScheduleDateTime(templateDatetime));
                        datetimeInput.val(freshScheduleDateTime(templateDatetime));
                        const pastError = modal.getRoot().find('#schedule-past-error');
                        datetimeInput.on('input change', function() {
                            pastError.hide();
                        });

                        const timezone = modal.getRoot().find('#timezone-display').attr('data-moodle-timezone');
                        modal.getRoot().find('#timezone-display').text('Moodle timezone: ' + timezone);

                        modal.getRoot().on(ModalEvents.save, function() {
                            const datetime = modal.getRoot().find('#scheduledfor-input').val();
                            const minSchedule = minimumScheduleDateTime(templateDatetime);
                            datetimeInput.attr('min', minSchedule);
                            if (!datetime || datetime < minSchedule) {
                                pastError.show();
                                return;
                            }

                            pastError.hide();
                            $('#schedule-userids').val(selected.join(','));
                            $('#schedule-datetime').val(datetime);
                            $('#schedule-form').submit();
                        });

                        modal.show();
                        return modal;
                    });
                });
            }

            const cancelSelectedBtn = document.getElementById('cancel-selected-btn');
            if (cancelSelectedBtn) {
                cancelSelectedBtn.addEventListener('click', function() {
                    const selected = Array.from(document.querySelectorAll('.user-checkbox:checked'))
                        .map(cb => cb.value);

                    if (selected.length === 0) {
                        alert('No users selected');
                        return;
                    }

                    if (confirm('Cancel deployments for ' + selected.length + ' selected users?')) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = M.cfg.wwwroot + '/mod/crucible/manage_deployments_action.php';

                        const addField = (name, value) => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = name;
                            input.value = value;
                            form.appendChild(input);
                        };

                        addField('action', 'cancel_selected');
                        addField('id', cmid);
                        addField('userids', selected.join(','));
                        addField('sesskey', sesskey);

                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }

            const endSelectedBtn = document.getElementById('end-selected-btn');
            if (endSelectedBtn) {
                endSelectedBtn.addEventListener('click', function() {
                    const selected = Array.from(document.querySelectorAll('.user-checkbox:checked'))
                        .map(cb => cb.value);

                    if (selected.length === 0) {
                        alert('No users selected');
                        return;
                    }

                    if (confirm('End active attempts for ' + selected.length + ' selected users?')) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = M.cfg.wwwroot + '/mod/crucible/manage_deployments_action.php';

                        const addField = (name, value) => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = name;
                            input.value = value;
                            form.appendChild(input);
                        };

                        addField('action', 'end_selected');
                        addField('id', cmid);
                        addField('userids', selected.join(','));
                        addField('sesskey', sesskey);

                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            };

            const extendSelectedBtn = document.getElementById('extend-selected-btn');
            if (extendSelectedBtn) {
                extendSelectedBtn.addEventListener('click', function() {
                    const selected = Array.from(document.querySelectorAll('.user-checkbox:checked'))
                        .filter(cb => {
                            const row = cb.closest('tr');
                            return row && rowStatus(row) === 'in progress';
                        })
                        .map(cb => cb.value);

                    if (selected.length === 0) {
                        alert('No active labs selected');
                        return;
                    }

                    ModalSaveCancel.create({
                        title: 'Extend Selected (' + selected.length + ')',
                        body: $('#extend-modal-content').html()
                    }).then(function(modal) {
                        modal.setSaveButtonText('Extend');

                        const extendIntervalInput = modal.getRoot().find('#extend-interval-input');
                        extendIntervalInput.on('keydown', function(e) {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                e.stopPropagation();
                            }
                        });

                        modal.getRoot().on(ModalEvents.save, function(e) {
                            const input = extendIntervalInput[0];
                            if (input && !input.checkValidity()) {
                                e.preventDefault();
                                input.reportValidity();
                                return;
                            }
                            const extendInterval = extendIntervalInput.val();
                            $('#extend-userids').val(selected.join(','));
                            $('#extend-interval').val(extendInterval);
                            $('#extend-form').submit();
                        });

                        modal.show();
                        return modal;
                    });
                });
            }

            const poll = async () => {
                try {
                    const url = M.cfg.wwwroot + '/mod/crucible/manage_deployments_status_ajax.php?cmid='
                        + encodeURIComponent(cmid) + '&sesskey=' + encodeURIComponent(sesskey);
                    const resp = await fetch(url, {credentials: 'same-origin'});
                    const data = await resp.json();

                    if (Array.isArray(data.updated_users)) {
                        data.updated_users.forEach(applyUpdate);
                    }
                    updateNotification(data);
                    updateBulkActionButtons();

                    const hasActive = hasActiveDeploys();
                    if (!hasActive) {
                        inactivePollCount++;
                        if (inactivePollCount >= MAX_INACTIVE_POLLS) {
                            stop();
                            return;
                        }
                    } else {
                        inactivePollCount = 0;
                    }

                    timer = window.setTimeout(poll, POLL_MS);
                } catch (e) {
                    // eslint-disable-next-line no-console
                    console.error('[Manage] Poll error:', e);
                    stop();
                }
            };

            // Always start polling on page load
            timer = window.setTimeout(poll, POLL_MS);
        }
    };
});
