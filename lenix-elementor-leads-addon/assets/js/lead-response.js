// Ensure leadResponseVars exists

jQuery(document).ready(function($) {
    // Render a notice using text nodes only (never inject strings as HTML)
    function showNotice($target, type, text) {
        $target.empty().append(
            $('<div>').addClass('notice notice-' + type).append($('<p>').text(text))
        ).show();
    }
    
    // Debug: Check if leadResponseVars exists
    if (typeof window.leadResponseVars === 'undefined') {
        return;
    }
    
    // Debug: Check if nonce exists
    if (!window.leadResponseVars.nonce) {
        return;
    }
    
    // leadResponseVars is ready
    
    // Show/hide email-specific fields
    function toggleEmailFields() {
        if ($('#response_type').val() === 'email') {
            $('#email_fields').show();
        } else {
            $('#email_fields').hide();
        }
    }

    // Initial toggle on page load (in case the default is email)
    toggleEmailFields();

    // Delegate change event (works even if form added later)
    $(document).on('change', '#response_type', toggleEmailFields);

    var $formDebug = $('#lead-response-form');
    
    // Handle form submission
    $('#submit-response').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $form = $button.closest('form');
        if ($form.length === 0) {
            return;
        }
        var $spinner = $form.find('.spinner');
        var $message = $('#response-message');
        
        // Debug: Check if nonce exists
        if (!window.leadResponseVars || !window.leadResponseVars.nonce) {
            showNotice($message, 'error', 'Security check failed: Nonce is missing');
            return;
        }
        
        // Get form values with safe handling
        var $responseTypeField = $form.find('[name="response_type"]');
        var $contentField = $form.find('[name="response_content"]');
        var $toField = $form.find('[name="response_to"]');
        var $subjectField = $form.find('[name="response_subject"]');
        
        // Get trimmed values
        var responseType = $responseTypeField.val() || '';
        var content      = ($contentField.val() || '').trim();
        var to           = ($toField.val() || '').trim();
        var subject      = ($subjectField.val() || '').trim();
        
        // Validate form
        if (!content) {
            showNotice($message, 'error', 'Please enter response content');
            $contentField.focus();
            return;
        }
        
        if (responseType === 'email') {
            if (!to) {
                showNotice($message, 'error', 'Please enter recipient email');
                return;
            }
            if (!subject) {
                showNotice($message, 'error', 'Please enter email subject');
                return;
            }
        }
        
        // Disable button and show spinner
        $button.prop('disabled', true);
        $spinner.css('visibility', 'visible');
        $message.hide();
        
        // Prepare AJAX data
        var ajaxData = {
            action: 'lenix_submit_response_ajax',
            nonce: window.leadResponseVars.nonce,
            lead_id: $form.find('[name="lead_id"]').val(),
            response_type: responseType,
            response_content: content,
            response_to: to,
            response_subject: subject
        };
        
        // Send AJAX request
        $.ajax({
            url: window.leadResponseVars.ajaxurl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    showNotice($message, 'success', response.data.message);
                    $form.find('[name="response_content"], [name="response_subject"]').val('');
                    // Reload the page to show the new response
                    location.reload();
                } else {
                    showNotice($message, 'error', response.data.message);
                }
            },
            error: function() {
                showNotice($message, 'error', 'An error occurred');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.css('visibility', 'hidden');
            }
        });
    });

    // Function to load response history
    function loadResponseHistory() {
        var data = {
            action: 'lenix_get_response_history',
            lead_id: $('[name="lead_id"]').val(),
            nonce: window.leadResponseVars.nonce
        };

        $.ajax({
            url: window.leadResponseVars.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    $('.response-history').html(response.data.html);
                } else {
                    // Fail silently
                }
            },
            error: function() {
                // Fail silently
            }
        });
    }

    // Load response history on page load
    loadResponseHistory();
}); 