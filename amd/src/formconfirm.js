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

define(['jquery', 'core/modal_factory', 'core/modal_events', 'core/str'], function($, ModalFactory, ModalEvents, Str) {

    /**
     * Initialize the form confirmation modals
     */
    var init = function() {
        const launchBtn = document.getElementById('launch_button');
        const endBtn = document.getElementById('end_button');
        const startConfirm = document.getElementById('start_confirmed');
        const stopConfirm = document.getElementById('stop_confirmed');
        const form = document.getElementById('lab_form');

        if (launchBtn) {
            launchBtn.addEventListener('click', function(e) {
                e.preventDefault();

                Str.get_strings([
                    {key: 'start_attempt_confirm', component: 'mod_crucible'},
                    {key: 'confirm', component: 'moodle'},
                    {key: 'yes', component: 'moodle'},
                    {key: 'no', component: 'moodle'}
                ]).then(function(strings) {
                    return ModalFactory.create({
                        type: ModalFactory.types.SAVE_CANCEL,
                        title: strings[1], // Confirm
                        body: strings[0], // Are you sure you want to launch the lab?
                    });
                }).then(function(modal) {
                    modal.setSaveButtonText(strings[2]); // Yes
                    modal.getRoot().on(ModalEvents.save, function() {
                        startConfirm.value = "yes";
                        form.submit();
                    });
                    modal.show();
                    return;
                }).catch(function(error) {
                    // Fallback to native confirm if modal fails
                    if (confirm(strings[0])) {
                        startConfirm.value = "yes";
                        form.submit();
                    }
                });
            });
        }

        if (endBtn) {
            endBtn.addEventListener('click', function(e) {
                e.preventDefault();

                Str.get_strings([
                    {key: 'stop_attempt_confirm', component: 'mod_crucible'},
                    {key: 'confirm', component: 'moodle'},
                    {key: 'yes', component: 'moodle'},
                    {key: 'no', component: 'moodle'}
                ]).then(function(strings) {
                    return ModalFactory.create({
                        type: ModalFactory.types.SAVE_CANCEL,
                        title: strings[1], // Confirm
                        body: strings[0], // Are you sure you want to end the lab?
                    });
                }).then(function(modal) {
                    modal.setSaveButtonText(strings[2]); // Yes
                    modal.getRoot().on(ModalEvents.save, function() {
                        stopConfirm.value = "yes";
                        form.submit();
                    });
                    modal.show();
                    return;
                }).catch(function(error) {
                    // Fallback to native confirm if modal fails
                    if (confirm(strings[0])) {
                        stopConfirm.value = "yes";
                        form.submit();
                    }
                });
            });
        }
    };

    return {
        init: init
    };
});
