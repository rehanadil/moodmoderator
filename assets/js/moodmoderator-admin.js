/**
 * MoodModerator Admin JavaScript
 *
 * @package MoodModerator
 */

(function($) {
    'use strict';

    /**
     * Handle tone approval
     */
    $(document).on('click', '.moodmoderator-approve-tone', function(e) {
        e.preventDefault();

        var $button = $(this);
        var suggestionId = $button.data('id');
        var $row = $button.closest('tr');

        if (!suggestionId) {
            return;
        }

        $button.prop('disabled', true).text('Processing...');

        $.ajax({
            url: moodModeratorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'moodmoderator_approve_tone',
                nonce: moodModeratorData.nonce,
                suggestion_id: suggestionId
            },
            success: function(response) {
                if (response.success) {
                    $row.addClass('approved');
                    $button.replaceWith('<span style="color: green;">✓ Approved</span>');
                    $row.find('.moodmoderator-reject-tone').remove();

                    // Show success message
                    showMessage('success', response.data.message);
                } else {
                    alert(response.data.message || 'Failed to approve tone');
                    $button.prop('disabled', false).text('Approve');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $button.prop('disabled', false).text('Approve');
            }
        });
    });

    /**
     * Handle tone rejection
     */
    $(document).on('click', '.moodmoderator-reject-tone', function(e) {
        e.preventDefault();

        var $button = $(this);
        var suggestionId = $button.data('id');
        var $row = $button.closest('tr');

        if (!suggestionId) {
            return;
        }

        $button.prop('disabled', true).text('Processing...');

        $.ajax({
            url: moodModeratorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'moodmoderator_reject_tone',
                nonce: moodModeratorData.nonce,
                suggestion_id: suggestionId
            },
            success: function(response) {
                if (response.success) {
                    $row.addClass('rejected');
                    $button.replaceWith('<span style="color: red;">✗ Rejected</span>');
                    $row.find('.moodmoderator-approve-tone').remove();

                    // Show success message
                    showMessage('success', response.data.message);
                } else {
                    alert(response.data.message || 'Failed to reject tone');
                    $button.prop('disabled', false).text('Reject');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $button.prop('disabled', false).text('Reject');
            }
        });
    });

    /**
     * Handle cache clearing
     */
    $(document).on('click', '#moodmoderator-clear-cache', function(e) {
        e.preventDefault();

        if (!confirm(moodModeratorData.strings.confirmClearCache)) {
            return;
        }

        var $button = $(this);
        $button.prop('disabled', true).text('Clearing...');

        $.ajax({
            url: moodModeratorData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'moodmoderator_clear_cache',
                nonce: moodModeratorData.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || 'Failed to clear cache');
                    $button.prop('disabled', false).text('Clear All Caches');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $button.prop('disabled', false).text('Clear All Caches');
            }
        });
    });

    /**
     * View metadata details
     */
    $(document).on('click', '.moodmoderator-view-metadata', function(e) {
        e.preventDefault();

        // Use .attr() to get the raw string, not .data() which auto-parses JSON
        var metadata = $(this).attr('data-metadata');

        if (!metadata) {
            return;
        }

        // Try to parse and pretty-print JSON
        try {
            var parsed = JSON.parse(metadata);
            var formatted = JSON.stringify(parsed, null, 2);
            alert(formatted);
        } catch(err) {
            // If parsing fails, just show the raw string
            alert(metadata);
        }
    });

    /**
     * Show success/error message
     */
    function showMessage(type, message) {
        var $message = $('<div class="moodmoderator-message ' + type + '">' + message + '</div>');
        $message.insertAfter('.wrap h1').delay(3000).fadeOut(function() {
            $(this).remove();
        });
    }

    /**
     * Toggle custom tones field based on strictness selection
     */
    $('#moodmoderator_strictness').on('change', function() {
        var $customTonesField = $('#moodmoderator_custom_tones_field');

        if ($(this).val() === 'custom') {
            $customTonesField.closest('tr').show();
        } else {
            $customTonesField.closest('tr').hide();
        }
    }).trigger('change');

})(jQuery);
