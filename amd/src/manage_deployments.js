define(['jquery', 'core/modal_save_cancel', 'core/modal_events'], function($, ModalSaveCancel, ModalEvents) {
    const POLL_MS = 5000;

    return {
        init: function(cmid, sesskey) {
            const root = document.querySelector('.mod-crucible-manage-deployments');
            if (!root) {
                return;
            }

            let timer = null;
            let lastSeenStatuses = new Map(); // Track status per user
            let inactivePollCount = 0;
            const MAX_INACTIVE_POLLS = 6; // Stop after 6 polls (30 seconds) with no activity

            const rowStatus = (row) => (row.getAttribute('data-status') || '').trim().toLowerCase();

            const hasActiveDeploys = () => {
                const table = document.querySelector('.mod-crucible-users-table tbody');
                if (!table) return false;
                const rows = table.querySelectorAll('tr');
                let hasActive = false;

                rows.forEach(row => {
                    const userid = row.getAttribute('data-userid');
                    const scheduledCell = row.querySelector('td:nth-child(6)');
                    if (userid) {
                        const status = rowStatus(row);
                        const scheduled = scheduledCell ? scheduledCell.textContent.trim() : '';
                        lastSeenStatuses.set(userid, status);
                        if (status === 'pending' || status === 'launched' || status === 'in progress' ||
                            (scheduled && scheduled !== '─')) {
                            hasActive = true;
                        }
                    }
                });

                return hasActive;
            };

            const stop = () => {
                if (timer) {
                    window.clearTimeout(timer);
                    timer = null;
                }
            };

            const updateBulkActionButtons = () => {
                const checkboxes = document.querySelectorAll('.user-checkbox:checked');
                const deployBtn = document.getElementById('deploy-selected-btn');
                const scheduleBtn = document.getElementById('schedule-selected-btn');
                const cancelBtn = document.getElementById('cancel-selected-btn');
                const endBtn = document.getElementById('end-selected-btn');

                // Count by status
                let canDeploy = 0, canCancel = 0, canEnd = 0;

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

                        const oneHourFromNow = new Date();
                        oneHourFromNow.setHours(oneHourFromNow.getHours() + 1);
                        const yyyy = oneHourFromNow.getFullYear();
                        const mm = String(oneHourFromNow.getMonth() + 1).padStart(2, '0');
                        const dd = String(oneHourFromNow.getDate()).padStart(2, '0');
                        const hh = String(oneHourFromNow.getHours()).padStart(2, '0');
                        const min = String(oneHourFromNow.getMinutes()).padStart(2, '0');
                        const defaultValue = yyyy + '-' + mm + '-' + dd + 'T' + hh + ':' + min;
                        modal.getRoot().find('#scheduledfor-input').val(defaultValue);

                        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
                        modal.getRoot().find('#timezone-display').text('Your timezone: ' + timezone);

                        modal.getRoot().on(ModalEvents.save, function() {
                            const datetime = modal.getRoot().find('#scheduledfor-input').val();
                            if (datetime) {
                                const timestamp = Math.floor(new Date(datetime).getTime() / 1000);
                                $('#schedule-userids').val(selected.join(','));
                                $('#schedule-timestamp').val(timestamp);
                                $('#schedule-form').submit();
                            }
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

            const poll = async () => {
                try {
                    const url = M.cfg.wwwroot + '/mod/crucible/manage_deployments_status_ajax.php?cmid='
                        + encodeURIComponent(cmid) + '&sesskey=' + encodeURIComponent(sesskey);
                    console.log('[Manage] Polling status');
                    const resp = await fetch(url, {credentials: 'same-origin'});
                    const data = await resp.json();

                    // Check if any users changed status
                    let hasChanges = false;
                    if (data.updated_users) {
                        for (const user of data.updated_users) {
                            // Determine new status (attempt takes priority)
                            let newStatus = 'none';
                            if (user.attemptstate) {
                                const statemap = {
                                    'inprogress': 'in progress',
                                    'finished': 'finished',
                                    'abandoned': 'abandoned',
                                    'overdue': 'overdue'
                                };
                                newStatus = (statemap[user.attemptstate] || user.attemptstate).toLowerCase();
                            } else if (user.deploystatus) {
                                newStatus = user.deploystatus.toLowerCase();
                            }

                            const oldStatus = lastSeenStatuses.get(user.userid.toString());

                            // If status changed from pending/launched/in progress to something else, reload
                            if (oldStatus &&
                                (oldStatus === 'pending' || oldStatus === 'launched' || oldStatus === 'in progress') &&
                                newStatus !== oldStatus) {
                                console.log('[Manage] User ' + user.userid + ' changed from ' + oldStatus + ' to ' + newStatus);
                                hasChanges = true;
                                break;
                            }
                        }
                    }

                    if (hasChanges) {
                        console.log('[Manage] Status changed, reloading page');
                        window.location.reload();
                        return;
                    }

                    // Check if page still has pending/launched/in progress/scheduled
                    const hasActive = hasActiveDeploys();
                    if (!hasActive) {
                        inactivePollCount++;
                        if (inactivePollCount >= MAX_INACTIVE_POLLS) {
                            console.log('[Manage] No active deploys for ' + MAX_INACTIVE_POLLS + ' polls, stopping');
                            stop();
                            return;
                        }
                    } else {
                        inactivePollCount = 0;
                    }

                    // Continue polling
                    timer = window.setTimeout(poll, POLL_MS);

                } catch (e) {
                    console.error('[Manage] Poll error:', e);
                    stop();
                }
            };

            // Always start polling on page load
            timer = window.setTimeout(poll, POLL_MS);
        }
    };
});
