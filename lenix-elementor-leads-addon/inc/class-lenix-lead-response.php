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
     * Validate the request and store the response (and send the email when needed).
     * Returns the saved response array or a WP_Error.
     */
    private function process_response($request) {
        $lead_id = isset($request['lead_id']) ? absint($request['lead_id']) : 0;
        if (!$lead_id || 'elementor_lead' !== get_post_type($lead_id)) {
            return new WP_Error('invalid_lead', __('Invalid lead ID', 'elementor-leads'));
        }

        // Check if user has permission to edit leads
        $edit_cap = get_option('elementor_leads_edit_role', 'edit_others_posts');
        if (!current_user_can($edit_cap) || !current_user_can('edit_post', $lead_id)) {
            return new WP_Error('permission', __('You do not have permission to edit leads', 'elementor-leads'));
        }

        // Get response data
        $allowed_types = array('note', 'email', 'call', 'sms');
        $response_type = isset($request['response_type']) ? sanitize_key(wp_unslash($request['response_type'])) : 'note';
        if (!in_array($response_type, $allowed_types, true)) {
            $response_type = 'note';
        }
        $response_content = isset($request['response_content']) ? wp_kses_post(wp_unslash($request['response_content'])) : '';
        $response_to = isset($request['response_to']) ? sanitize_email(wp_unslash($request['response_to'])) : '';
        $response_subject = isset($request['response_subject']) ? sanitize_text_field(wp_unslash($request['response_subject'])) : '';

        if (empty($response_content)) {
            return new WP_Error('empty_content', __('Response content cannot be empty.', 'elementor-leads'));
        }

        $response_data = array(
            'type' => $response_type,
            'content' => $response_content,
            'message' => $response_content, // compatibility with older history entries
            'user' => wp_get_current_user()->display_name,
            'time' => time()
        );

        // Add email-specific data if it's an email response
        if ($response_type === 'email' && !empty($response_to) && is_email($response_to)) {
            $response_data['to'] = $response_to;
            $response_data['sent_to'] = $response_to;
            $response_data['subject'] = $response_subject;

            // Get original inquiry details for email template
            $lead_data = get_post_meta($lead_id, 'lead_data', true);
            if (is_string($lead_data)) {
                $lead_data = json_decode($lead_data, true);
            }

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

            if (function_exists('lenix_get_email_template')) {
                $email_content = lenix_get_email_template($response_subject, $response_content, $original_inquiry);
            } else {
                $email_content = $response_content;
            }

            // Send email with noreply address under current domain
            $site_domain = wp_parse_url(home_url(), PHP_URL_HOST);
            $from_email  = sanitize_email('noreply@' . $site_domain);
            // Fallback to admin email if the constructed address is invalid
            if (!is_email($from_email)) {
                $from_email = get_bloginfo('admin_email');
            }

            // Strip anything that could break out of the header
            $from_name = str_replace(array("\r", "\n", '<', '>'), '', get_bloginfo('name'));
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $from_name . ' <' . $from_email . '>'
            );

            $response_data['email_sent'] = wp_mail($response_to, $response_subject, $email_content, $headers);
        }

        $responses = get_post_meta($lead_id, 'response_history', true);
        if (!is_array($responses)) {
            $responses = array();
        }
        $responses[] = $response_data;
        update_post_meta($lead_id, 'response_history', $responses);

        return $response_data;
    }

    /**
     * Handle response submission
     */
    public function handle_response_submission() {
        // Verify nonce
        if (!isset($_POST['lenix_response_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lenix_response_nonce'])), 'lenix_response_action')) {
            wp_die(esc_html__('Security check failed', 'elementor-leads'), '', array('response' => 403));
        }

        $lead_id = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        $result = $this->process_response($_POST);

        $args = array('post' => $lead_id, 'action' => 'edit');
        if (is_wp_error($result)) {
            $args['lenix_error'] = $result->get_error_code() === 'empty_content' ? 'empty_content' : 'permission';
        } else {
            $args['message'] = 'response_added';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('post.php')));
        exit;
    }

    /**
     * Handle response submission via AJAX
     */
    public function handle_response_submission_ajax() {
        check_ajax_referer('lenix_response_action', 'nonce');

        $result = $this->process_response($_POST);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Response added successfully', 'elementor-leads')));
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
        wp_add_inline_script('lead-response', 'window.leadResponseVars = ' . wp_json_encode($script_data) . ';', 'before');

        // Enqueue script
        wp_enqueue_script('lead-response');
        
        $scripts_enqueued = true;
    }

    /**
     * Get response history via AJAX
     */
    public function ajax_get_response_history() {
        check_ajax_referer('lenix_response_action', 'nonce');
        
        $lead_id = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        if (!$lead_id || 'elementor_lead' !== get_post_type($lead_id)) {
            wp_send_json_error(array('message' => 'Invalid lead ID'));
        }
        
        // Only users allowed to view leads can read the history
        $view_cap = get_option('elementor_leads_view_role', 'edit_posts');
        if (!current_user_can($view_cap) || !current_user_can('edit_post', $lead_id)) {
            wp_send_json_error(array('message' => 'Permission denied'), 403);
        }
        
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