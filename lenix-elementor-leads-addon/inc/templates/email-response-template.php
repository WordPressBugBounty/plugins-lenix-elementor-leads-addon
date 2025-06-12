<?php
if (!defined('ABSPATH')) exit;

function lenix_get_email_template($subject, $message, $original_inquiry = array()) {
    $site_name = get_bloginfo('name');
    $site_url = get_bloginfo('url');
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
    </head>
    <body style="background-color: #f6f6f6; font-family: Arial, sans-serif; margin: 0; padding: 0;">
        <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
            <!-- Header -->
            <div style="text-align: center; padding: 20px 0; border-bottom: 2px solid #eee;">
                <h1 style="color: #444; margin: 0; font-size: 24px;">
                    <?php _e('Response to Your Inquiry', 'elementor-leads'); ?>
                </h1>
            </div>

            <!-- Content -->
            <div style="padding: 20px 0;">
                <h2 style="color: #666; font-size: 18px;">
                    <?php echo esc_html($subject); ?>
                </h2>
                
                <div style="color: #444; line-height: 1.6; margin: 20px 0;">
                    <?php echo wp_kses_post(wpautop($message)); ?>
                </div>

                <?php if (!empty($original_inquiry)): ?>
                    <div style="margin-top: 30px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                        <h3 style="color: #666; font-size: 16px; margin-top: 0;">
                            <?php _e('Your Original Inquiry', 'elementor-leads'); ?>
                        </h3>
                        <?php foreach ($original_inquiry as $field): ?>
                            <p style="margin: 10px 0;">
                                <strong style="color: #555;"><?php echo esc_html($field['label']); ?>:</strong>
                                <span style="color: #666;"><?php echo esc_html($field['value']); ?></span>
                            </p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div style="text-align: center; padding-top: 20px; border-top: 2px solid #eee; color: #666; font-size: 12px;">
                <p>
                    <?php printf(
                        __('This is a response from %s', 'elementor-leads'),
                        sprintf('<a href="%s" style="color: #444; text-decoration: none;">%s</a>', esc_url($site_url), esc_html($site_name))
                    ); ?>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
} 