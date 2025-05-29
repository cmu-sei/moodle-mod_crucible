define(['core/log', 'core/modal_factory'], function(log, ModalFactory) {

    return {
        init: function(info) {
            var copyButton = document.getElementById('copy_invite');
            var linkText = document.getElementById('invitationlinkurl');

            if (!copyButton || !linkText) {
                log.debug('Invite button or link not found.');
                return;
            }

            copyButton.onclick = function() {
                var link = linkText.textContent;

                // Try to copy to clipboard
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(link)
                        .then(() => {
                            showModal('Invitation Link Copied', link);
                        })
                        .catch(err => {
                            log.debug('Clipboard error:', err);
                            showModal('Please Copy Invitation Link', link);
                        });
                } else {
                    showModal('Please Copy Invitation Link', link);
                }
            };

            function showModal(title, body) {
                ModalFactory.create({
                    type: ModalFactory.types.ALERT,
                    title: title,
                    body: body,
                    removeOnClose: true,
                }).then(modal => modal.show())
                  .catch(err => log.debug(err));
            }
        }
    };
});