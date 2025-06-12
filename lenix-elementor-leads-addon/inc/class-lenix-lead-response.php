<?php
if (!defined('ABSPATH')) exit;

class Lenix_Lead_Response {
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_response_meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_response_scripts'), 20);
        
        add_action('admin_post_lenix_submit_response', array($this, 'handle_response_submission'));
        
        add_action('wp_ajax_lenix_submit_response_ajax', array($this, 'handle_response_submission_ajax'));
        
        // Load email template
        require_once(ELEMENTOR_LEADS_PATH . 'inc/templates/email-response-template.php');
        
        // Add AJAX handler for getting response history
        add_action('wp_ajax_lenix_get_response_history', array($this, 'ajax_get_response_history'));
    }

    /**
     * Add response meta box
     */
    public function add_response_meta_box() {
        // Check if user has permission to view leads
        $view_cap = get_option('elementor_leads_view_role', 'edit_posts');
        if (!current_user_can($view_cap)) {
            return;
        }
        
        add_meta_box(
            'lenix_lead_response',
            __('Response to Lead', 'elementor-leads'),
            array($this, 'render_response_meta_box'),
            'elementor_lead',
            'normal',
            'high'
        );
    }

    /**
     * Render response meta box
     */
    public function render_response_meta_box($post) {
        // Include the template without debug scripts
        include(ELEMENTOR_LEADS_PATH . 'templates/lead-response.php');
    }

    /**
     * Handle response submission
     */
    public function handle_response_submission() {
        // Verify nonce
        if (!isset($_POST['lenix_response_nonce']) || !wp_verify_nonce($_POST['lenix_response_nonce'], 'lenix_response_action')) {
            wp_die(__('Security check failed', 'elementor-leads'));
        }
        
        // Check if lead ID is set
        if (!isset($_POST['lead_id']) || empty($_POST['lead_id'])) {
            wp_die(__('Invalid lead ID', 'elementor-leads'));
        }
        
        $lead_id = intval($_POST['lead_id']);
        
        // Check if user has permission to edit leads
        $edit_cap = get_option('elementor_leads_edit_role', 'edit_others_posts');
        if (!current_user_can($edit_cap)) {
            // Redirect back with error
            wp_redirect(add_query_arg(
                array('post' => $lead_id, 'action' => 'edit', 'lenix_error' => 'permission'),
                admin_url('post.php')
            ));
            exit;
        }
        
        // Get response data
        $response_type = sanitize_text_field($_POST['response_type']);
        $response_content = wp_kses_post($_POST['response_content']);
        $response_to = isset($_POST['response_to']) ? sanitize_email($_POST['response_to']) : '';
        $response_subject = isset($_POST['response_subject']) ? sanitize_text_field($_POST['response_subject']) : '';
        
        // Validate data
        if (empty($response_content)) {
            wp_redirect(add_query_arg(
                array('post' => $lead_id, 'action' => 'edit', 'lenix_error' => 'empty_content'),
                admin_url('post.php')
            ));
            exit;
        }
        
        // Get current user info
        $current_user = wp_get_current_user();
        $user_display_name = $current_user->display_name;
        
        // Create response data
        $response_data = array(
            'type' => $response_type,
            'content' => $response_content,
            'user' => $user_display_name,
            'time' => time()
        );
        
        // Add email-specific data if it's an email response
        if ($response_type === 'email' && !empty($response_to)) {
            $response_data['to'] = $response_to;
            $response_data['subject'] = $response_subject;
            
            // Get original inquiry details for email template
            $lead_data = get_post_meta($lead_id, 'lead_data', true);
            if (is_string($lead_data)) {
                $lead_data = json_decode($lead_data, true);
            }
            
            // Format original inquiry data
            $original_inquiry = array();
            if (is_array($lead_data)) {
                foreach ($lead_data as $key => $field) {
                    if (is_array($field) && isset($field['value']) && !empty($field['value'])) {
                        $original_inquiry[] = array(
                            'label' => isset($field['title']) ? $field['title'] : $key,
                            'value' => $field['value']
                        );
                    }
                }
            }

            // Get formatted email content
            if (function_exists('lenix_get_email_template')) {
                $email_content = lenix_get_email_template($response_subject, $response_content, $original_inquiry);
            } else {
                $email_content = $response_content;
            }

            // Send email with noreply address under current domain
            $site_domain = parse_url( home_url(), PHP_URL_HOST );
            $from_email  = 'noreply@' . $site_domain;
            // Sanitize and validate the constructed address; fallback to admin email if invalid
            $from_email  = sanitize_email( $from_email );
            if ( ! is_email( $from_email ) ) {
                $from_email = get_bloginfo( 'admin_email' );
            }

            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_bloginfo( 'name' ) . ' <' . $from_email . '>'
            );
            
            $sent = wp_mail($response_to, $response_subject, $email_content, $headers);
            
            // Add sent status to response data
            $response_data['email_sent'] = $sent;
        }
        
        // Get existing responses
        $responses = get_post_meta($lead_id, 'response_history', true);
        if (!is_array($responses)) {
            $responses = array();
        }
        
        // Add new response
        $responses[] = $response_data;
        
        // Save updated responses
        update_post_meta($lead_id, 'response_history', $responses);
        
        // Redirect back to lead edit page with success message
        wp_redirect(add_query_arg(
            array('post' => $lead_id, 'action' => 'edit', 'message' => 'response_added'),
            admin_url('post.php')
        ));
        exit;
    }

    /**
     * Handle response submission via AJAX
     */
    public function handle_response_submission_ajax() {
        try {
            // Check if nonce exists
            if (!isset($_POST['nonce'])) {
                wp_send_json_error(array('message' => 'Security check failed: Nonce is missing'));
                return;
            }
            
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'lenix_response_action')) {
                wp_send_json_error(array('message' => 'Security check failed: Invalid nonce'));
                return;
            }
            
            // Check if lead ID is set
            if (!isset($_POST['lead_id']) || empty($_POST['lead_id'])) {
                wp_send_json_error(array('message' => 'Invalid lead ID'));
                return;
            }
            
            $lead_id = intval($_POST['lead_id']);
            
            // Check if user has permission to edit leads
            $edit_cap = get_option('elementor_leads_edit_role', 'edit_others_posts');
            if (!current_user_can($edit_cap)) {
                wp_send_json_error(array('message' => 'You do not have permission to edit leads'));
                return;
            }
            
            // Get response data
            $response_type = sanitize_text_field($_POST['response_type']);
            $response_content = wp_kses_post($_POST['response_content']);
            $response_to = isset($_POST['response_to']) ? sanitize_email($_POST['response_to']) : '';
            $response_subject = isset($_POST['response_subject']) ? sanitize_text_field($_POST['response_subject']) : '';
            
            // Validate data
            if (empty($response_content)) {
                wp_send_json_error(array('message' => 'Response content cannot be empty'));
                return;
            }
            
            // Get current user info
            $current_user = wp_get_current_user();
            $user_display_name = $current_user->display_name;
            
            // Create response data
            $response_data = array(
                'type' => $response_type,
                'content' => $response_content,
                'message' => $response_content, // Add message field for compatibility
                'user' => $user_display_name,
                'time' => time()
            );
            
            // Add email-specific data if it's an email response
            if ($response_type === 'email' && !empty($response_to)) {
                $response_data['to'] = $response_to;
                $response_data['sent_to'] = $response_to; // Add sent_to field for compatibility
                $response_data['subject'] = $response_subject;
                
                // Get original inquiry details for email template
                $lead_data = get_post_meta($lead_id, 'lead_data', true);
                if (is_string($lead_data)) {
                    $lead_data = json_decode($lead_data, true);
                }
                
                // Format original inquiry data
                $original_inquiry = array();
                if (is_array($lead_data)) {
                    foreach ($lead_data as $key => $field) {
                        if (is_array($field) && isset($field['value']) && !empty($field['value'])) {
                            $original_inquiry[] = array(
                                'label' => isset($field['title']) ? $field['title'] : $key,
                                'value' => $field['value']
                            );
                        }
                    }
                }

                // Get formatted email content
                if (function_exists('lenix_get_email_template')) {
                    $email_content = lenix_get_email_template($response_subject, $response_content, $original_inquiry);
                } else {
                    $email_content = $response_content;
                }

                // Send email with noreply address under current domain
                $site_domain = parse_url( home_url(), PHP_URL_HOST );
                $from_email  = 'noreply@' . $site_domain;
                // Sanitize and validate the constructed address; fallback to admin email if invalid
                $from_email  = sanitize_email( $from_email );
                if ( ! is_email( $from_email ) ) {
                    $from_email = get_bloginfo( 'admin_email' );
                }

                $headers = array(
                    'Content-Type: text/html; charset=UTF-8',
                    'From: ' . get_bloginfo( 'name' ) . ' <' . $from_email . '>'
                );
                
                $sent = wp_mail($response_to, $response_subject, $email_content, $headers);
                
                // Add sent status to response data
                $response_data['email_sent'] = $sent;
            }
            
            // Get existing responses
            $responses = get_post_meta($lead_id, 'response_history', true);
            if (!is_array($responses)) {
                $responses = array();
            }
            
            // Add new response
            $responses[] = $response_data;
            
            // Save updated responses
            update_post_meta($lead_id, 'response_history', $responses);
            
            wp_send_json_success(array('message' => 'Response added successfully'));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
    }

    public function enqueue_response_scripts($hook) {
        static $scripts_enqueued = false;
        
        if ($scripts_enqueued) {
            return;
        }
        
        // Check if we're on the right page
        if ('post.php' !== $hook || get_post_type() !== 'elementor_lead') {
            return;
        }

        // Create nonce first
        $nonce = wp_create_nonce('lenix_response_action');

        // Prepare data
        $script_data = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => $nonce
        );

        // Enqueue styles
        wp_enqueue_style(
            'lenix-lead-response',
            ELEMENTOR_LEADS_URL . 'assets/css/lead-response.css',
            array('dashicons'),
            ELEMENTOR_LEADS_VERSION
        );

        // Register script
        wp_register_script(
            'lead-response',
            ELEMENTOR_LEADS_URL . 'assets/js/lead-response.js',
            array('jquery'),
            ELEMENTOR_LEADS_VERSION,
            true
        );

        // Add inline script to define leadResponseVars before the main script (no debug)
        wp_add_inline_script('lead-response', 'window.leadResponseVars = ' . json_encode($script_data) . ';', 'before');

        // Enqueue script
        wp_enqueue_script('lead-response');
        
        $scripts_enqueued = true;
    }

    /**
     * Get response history via AJAX
     */
    public function ajax_get_response_history() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lenix_response_action')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        // Check if lead ID is set
        if (!isset($_POST['lead_id']) || empty($_POST['lead_id'])) {
            wp_send_json_error(array('message' => 'Invalid lead ID'));
            return;
        }
        
        $lead_id = intval($_POST['lead_id']);
        
        // Get response history
        $responses = get_post_meta($lead_id, 'response_history', true);
        if (!is_array($responses)) {
            $responses = array();
        }
        
        // Start output buffering
        ob_start();
        
        // Include the template for response history
        include(ELEMENTOR_LEADS_PATH . 'templates/response-history.php');
        
        // Get the output
        $html = ob_get_clean();
        
        // Send the response
        wp_send_json_success(array('html' => $html));
    }
}

new Lenix_Lead_Response(); 