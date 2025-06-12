jQuery(document).ready(function($) {
    // Modify the form submission to prevent redirect
    $('#post').on('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        var formData = new FormData(this);
        
        // Add action
        formData.append('action', 'save_lead_data');
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('#message').remove();
                    $('#wpbody-content').prepend('<div id="message" class="updated notice is-dismissible"><p>' + response.data.message + '</p></div>');
                } else {
                    // Show error message
                    $('#message').remove();
                    $('#wpbody-content').prepend('<div id="message" class="error notice is-dismissible"><p>' + response.data.message + '</p></div>');
                }
            }
        });
    });
}); 