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

define(['core/log', 'core/modal'], function(log, Modal) {

    return {
        init: function(info) {
            var copyButton = document.getElementById('copy_invite');
            if (copyButton) {
                copyButton.onclick = function() {
                    var textElement = document.getElementById('invitationlinkurl');
                    if (!textElement) {
                        log.debug('Invitation link element not found.');
                        return;
                    }

                    var text = textElement.value || textElement.innerText || textElement.textContent || '';

                    if (textElement.select) {
                        textElement.select();
                    }

                    try {
                        var success = document.execCommand('copy');

                        Modal.create({
                            title: success ? 'Invitation Link Copied to Clipboard' : 'Please Copy Invitation Link',
                            body: text,
                            removeOnClose: true,
                            show: true
                        }).catch(function(err) {
                            log.debug(err);
                        });
                    } catch (err) {
                        log.debug('Clipboard copy failed:', err);
                        Modal.create({
                            title: 'Please Copy Invitation Link',
                            body: text,
                            removeOnClose: true,
                            show: true
                        }).catch(function(err) {
                            log.debug(err);
                        });
                    }
                };
            }
        }
    };
});