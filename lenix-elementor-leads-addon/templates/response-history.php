<h3><?php _e('Response History', 'elementor-leads'); ?></h3>

<?php if (!empty($responses)): ?>
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
                    <strong><?php echo esc_html($type_label); ?></strong>
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
                    echo wpautop($response['message']);
                } elseif (isset($response['content'])) {
                    echo wpautop($response['content']);
                } else {
                    echo '<em>' . __('No content', 'elementor-leads') . '</em>';
                }
                ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <p><?php _e('No responses yet.', 'elementor-leads'); ?></p>
<?php endif; ?> 