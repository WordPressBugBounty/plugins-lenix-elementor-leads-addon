<?php
// Check if user has permission to edit leads
$edit_cap = get_option('elementor_leads_edit_role', 'edit_others_posts');
$can_edit = current_user_can($edit_cap);

// Get lead data
$lead_id = get_the_ID();

// Get response history
$responses = get_post_meta($lead_id, 'response_history', true);
if (!is_array($responses)) {
    $responses = array();
}

// Get lead email
$lead_data = get_post_meta($lead_id, 'lead_data', true);
if (is_string($lead_data)) {
    $lead_data = json_decode($lead_data, true);
}

$lead_email = '';
if (isset($lead_data['email']['value'])) {
    $lead_email = $lead_data['email']['value'];
} elseif (isset($lead_data['email'])) {
    $lead_email = $lead_data['email'];
}
?>

<div class="lenix-lead-response">
    <?php if (!empty($responses)): ?>
        <div class="response-history">
            <h3><?php _e('Response History', 'elementor-leads'); ?></h3>
            
            <?php foreach (array_reverse($responses) as $index => $response): ?>
                <?php
                // Get response type
                $type = isset($response['type']) ? $response['type'] : 'note';
                
                // Get response icon class (WordPress Dashicons)
                $icon_class = '';
                switch ($type) {
                    case 'email':
                        $icon_class = 'dashicons-email';
                        break;
                    case 'call':
                        $icon_class = 'dashicons-phone';
                        break;
                    case 'sms':
                        $icon_class = 'dashicons-format-chat';
                        break;
                    default:
                        $icon_class = 'dashicons-edit';
                }
                
                // Get response type label
                $type_label = '';
                switch ($type) {
                    case 'email':
                        $type_label = __('Email', 'elementor-leads');
                        break;
                    case 'call':
                        $type_label = __('Phone Call', 'elementor-leads');
                        break;
                    case 'sms':
                        $type_label = __('SMS', 'elementor-leads');
                        break;
                    default:
                        $type_label = __('Internal Note', 'elementor-leads');
                }
                ?>
                <div class="response-item type-<?php echo esc_attr($type); ?>">
                    <div class="response-header">
                        <div class="response-meta">
                            <span class="response-type-icon response-type-<?php echo esc_attr($type); ?>">
                                <span class="dashicons <?php echo esc_attr($icon_class); ?>"></span>
                            </span>
                            <strong><?php echo esc_html($type_label); ?> </strong>
                            <span class="response-user">
                                <?php 
                                if (!empty($response['user'])) {
                                    echo ' ' . __('by', 'elementor-leads') . ' ' . esc_html($response['user']);
                                }
                                ?>
                            </span>
                            <span class="response-time">
                                <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $response['time']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if (!empty($response['subject']) || !empty($response['sent_to']) || !empty($response['to'])): ?>
                        <div class="response-details">
                            <?php if (!empty($response['subject'])): ?>
                                <p><strong><?php _e('Subject:', 'elementor-leads'); ?></strong> <?php echo esc_html($response['subject']); ?></p>
                            <?php endif; ?>
                            
                            <?php 
                            $recipient = '';
                            if (!empty($response['sent_to'])) {
                                $recipient = $response['sent_to'];
                            } elseif (!empty($response['to'])) {
                                $recipient = $response['to'];
                            }
                            
                            if (!empty($recipient)):
                            ?>
                                <p><strong><?php _e('To:', 'elementor-leads'); ?></strong> <?php echo esc_html($recipient); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($type === 'email' && isset($response['email_sent'])): ?>
                                <p>
                                    <strong><?php _e('Status:', 'elementor-leads'); ?></strong> 
                                    <?php if ($response['email_sent']): ?>
                                        <span style="color: #28a745;">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <?php _e('Sent successfully', 'elementor-leads'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #dc3545;">
                                            <span class="dashicons dashicons-dismiss"></span>
                                            <?php _e('Failed to send', 'elementor-leads'); ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="response-content">
                        <?php 
                        if (isset($response['message'])) {
                            // Output plain text (no HTML) with line breaks preserved
                            echo nl2br( esc_html( $response['message'] ) );
                        } elseif (isset($response['content'])) {
                            // Output plain text (no HTML) with line breaks preserved
                            echo nl2br( esc_html( $response['content'] ) );
                        } else {
                            echo '<em>' . __('No content', 'elementor-leads') . '</em>';
                        }
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p><?php _e('No responses yet.', 'elementor-leads'); ?></p>
    <?php endif; ?>
    
    <?php if ($can_edit || 1): ?>
        <div class="add-response">
            <h3><?php _e('Add Response', 'elementor-leads'); ?></h3>
            
            <form id="lead-response-form" method="post">
                <input type="hidden" name="lead_id" value="<?php echo esc_attr($lead_id); ?>">
                
                <p>
                    <label for="response_type"><?php _e('Response Type', 'elementor-leads'); ?></label>
                    <select name="response_type" id="response_type">
                        <option value="note"><?php _e('Internal Note', 'elementor-leads'); ?></option>
                        <option value="email"><?php _e('Email', 'elementor-leads'); ?></option>
                        <option value="call"><?php _e('Phone Call', 'elementor-leads'); ?></option>
                        <option value="sms"><?php _e('SMS', 'elementor-leads'); ?></option>
                    </select>
                </p>
                
                <div id="email_fields" style="display:none;">
                    <p>
                        <label for="response_to"><?php _e('To', 'elementor-leads'); ?></label>
                        <input type="email" name="response_to" id="response_to" value="<?php echo esc_attr($lead_email); ?>">
                    </p>
                    
                    <p>
                        <label for="response_subject"><?php _e('Subject', 'elementor-leads'); ?></label>
                        <input type="text" name="response_subject" id="response_subject">
                    </p>
                </div>
                
                <p>
                    <label for="response_content"><?php _e('Content', 'elementor-leads'); ?></label>
                    <textarea name="response_content" id="response_content" rows="5"></textarea>
                </p>
                
                <p>
                    <button type="button" id="submit-response" class="button button-primary"><?php _e('Submit Response', 'elementor-leads'); ?></button>
                    <span class="spinner" style="float: none; visibility: hidden;"></span>
                </p>
                <div id="response-message" style="display: none;"></div>
            </form>
        </div>
    <?php else: ?>
        <div class="notice notice-warning inline">
            <p><?php _e('You do not have permission to add responses. Please contact your administrator for full access.', 'elementor-leads'); ?></p>
        </div>
    <?php endif; ?>
</div> 