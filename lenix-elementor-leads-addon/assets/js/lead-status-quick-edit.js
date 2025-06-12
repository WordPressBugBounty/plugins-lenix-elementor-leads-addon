jQuery(document).ready(function($) {
    // Handle status change in quick edit
    $('.quick-status-change').on('change', function() {
        var $this = $(this);
        var postId = $this.data('post-id');
        var newStatus = $this.find('option:selected').text();
        
        if (!newStatus) return;

        // Send AJAX request to update status
        $.ajax({
            url: leadStatusVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'update_lead_status',
                post_id: postId,
                status: newStatus,
                nonce: leadStatusVars.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Reload the page to show updated status
                    location.reload();
                }
            }
        });
    });
});
