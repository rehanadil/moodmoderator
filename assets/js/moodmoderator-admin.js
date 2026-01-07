/**
 * MoodModerator Admin JavaScript
 *
 * @package MoodModerator
 */

(function() {
    'use strict';

    const strings = (window.moodModeratorData && moodModeratorData.strings) ? moodModeratorData.strings : {};

    /**
     * Make AJAX request
     */
    function makeAjaxRequest(action, data, successCallback, errorCallback) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', moodModeratorData.nonce);

        // Add additional data
        for (const key in data) {
            if (data.hasOwnProperty(key)) {
                formData.append(key, data[key]);
            }
        }

        fetch(moodModeratorData.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                successCallback(data);
            } else {
                errorCallback(data);
            }
        })
        .catch(error => {
            console.error('AJAX Error:', error);
            errorCallback({ data: { message: strings.errorOccurred || 'An error occurred. Please try again.' } });
        });
    }

    /**
     * Show success/error message
     */
    function showMessage(type, message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'moodmoderator-message ' + type;
        messageDiv.textContent = message;

        const wrapHeading = document.querySelector('.wrap h1');
        if (wrapHeading) {
            wrapHeading.insertAdjacentElement('afterend', messageDiv);

            // Fade out and remove after 3 seconds
            setTimeout(() => {
                messageDiv.style.transition = 'opacity 0.5s';
                messageDiv.style.opacity = '0';
                setTimeout(() => {
                    messageDiv.remove();
                }, 500);
            }, 3000);
        }
    }

    /**
     * Handle tone approval
     */
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('moodmoderator-approve-tone')) {
            e.preventDefault();

            const button = e.target;
            const suggestionId = button.getAttribute('data-id');
            const row = button.closest('tr');

            if (!suggestionId) {
                return;
            }

            button.disabled = true;
            button.textContent = strings.processing || 'Processing...';

            makeAjaxRequest(
                'moodmoderator_approve_tone',
                { suggestion_id: suggestionId },
                function(response) {
                    row.classList.add('approved');
                    const approvedSpan = document.createElement('span');
                    approvedSpan.style.color = 'green';
                    approvedSpan.textContent = strings.approvedLabel || 'Approved';
                    button.replaceWith(approvedSpan);

                    const rejectButton = row.querySelector('.moodmoderator-reject-tone');
                    if (rejectButton) {
                        rejectButton.remove();
                    }

                    showMessage('success', response.data.message);
                },
                function(response) {
                    alert(response.data.message || strings.approveFailed || 'Failed to approve tone');
                    button.disabled = false;
                    button.textContent = strings.approve || 'Approve';
                }
            );
        }
    });

    /**
     * Handle tone rejection
     */
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('moodmoderator-reject-tone')) {
            e.preventDefault();

            const button = e.target;
            const suggestionId = button.getAttribute('data-id');
            const row = button.closest('tr');

            if (!suggestionId) {
                return;
            }

            button.disabled = true;
            button.textContent = strings.processing || 'Processing...';

            makeAjaxRequest(
                'moodmoderator_reject_tone',
                { suggestion_id: suggestionId },
                function(response) {
                    row.classList.add('rejected');
                    const rejectedSpan = document.createElement('span');
                    rejectedSpan.style.color = 'red';
                    rejectedSpan.textContent = strings.rejectedLabel || 'Rejected';
                    button.replaceWith(rejectedSpan);

                    const approveButton = row.querySelector('.moodmoderator-approve-tone');
                    if (approveButton) {
                        approveButton.remove();
                    }

                    showMessage('success', response.data.message);
                },
                function(response) {
                    alert(response.data.message || strings.rejectFailed || 'Failed to reject tone');
                    button.disabled = false;
                    button.textContent = strings.reject || 'Reject';
                }
            );
        }
    });

    /**
     * Handle cache clearing
     */
    document.addEventListener('click', function(e) {
        if (e.target.id === 'moodmoderator-clear-cache') {
            e.preventDefault();

            if (!confirm(moodModeratorData.strings.confirmClearCache)) {
                return;
            }

            const button = e.target;
            button.disabled = true;
            button.textContent = strings.clearing || 'Clearing...';

            makeAjaxRequest(
                'moodmoderator_clear_cache',
                {},
                function(response) {
                    alert(response.data.message);
                    location.reload();
                },
                function(response) {
                    alert(response.data.message || strings.clearFailed || 'Failed to clear cache');
                    button.disabled = false;
                    button.textContent = strings.clearAllCaches || 'Clear All Caches';
                }
            );
        }
    });

    /**
     * View metadata details
     */
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('moodmoderator-view-metadata')) {
            e.preventDefault();

            const metadata = e.target.getAttribute('data-metadata');

            if (!metadata) {
                return;
            }

            // Try to parse and pretty-print JSON
            try {
                const parsed = JSON.parse(metadata);
                const formatted = JSON.stringify(parsed, null, 2);
                alert(formatted);
            } catch(err) {
                // If parsing fails, just show the raw string
                alert(metadata);
            }
        }
    });

    /**
     * Toggle custom tones field based on strictness selection
     */
    document.addEventListener('DOMContentLoaded', function() {
        const strictnessSelect = document.getElementById('moodmoderator_strictness');

        if (strictnessSelect) {
            function toggleCustomTonesField() {
                const customTonesField = document.getElementById('moodmoderator_custom_tones_field');

                if (customTonesField) {
                    const row = customTonesField.closest('tr');
                    if (row) {
                        if (strictnessSelect.value === 'custom') {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                }
            }

            strictnessSelect.addEventListener('change', toggleCustomTonesField);

            // Trigger on load
            toggleCustomTonesField();
        }
    });

})();
