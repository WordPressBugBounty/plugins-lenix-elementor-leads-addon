<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Elementor_Leads_CF7_Handler {
    
    protected function insert_lead_post($form_id, $submission) {
        $form = WPCF7_ContactForm::get_instance($form_id);
        if (!$form) return;

        $form_fields = array();
        $submission_data = $submission->get_posted_data();
        
        // ארגון השדות בפורמט תואם למערכת הלידים
        foreach ($submission_data as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            
            $form_fields[$key] = array(
                'title' => $this->get_field_label($form, $key),
                'value' => $value,
                'type' => $this->get_field_type($form, $key),
                'id' => $key
            );
        }

        // מטא דאטה ללידים
        $meta = array(
            'lead_data' => wp_slash(wp_json_encode($form_fields)),
            'post_id' => get_the_ID(),
            'form_slug' => 'cf7_' . $form_id,
            'lead_version' => ELEMENTOR_LEADS_VERSION,
            'form_type' => 'cf7'
        );
        
        // יצירת הליד
        $lead_id = wp_insert_post(array(
            'post_type' => 'elementor_lead',
            'post_status' => 'publish',
            'post_title' => __('Lead #', 'elementor-leads'),
            'meta_input' => $meta,
        ));

        if ($lead_id) {
            wp_update_post(array(
                'ID' => $lead_id,
                'post_title' => __('Lead #', 'elementor-leads') . $lead_id
            ));
        }

        return $lead_id;
    }

    protected function get_field_label($form, $field_name) {
        $form_tags = $form->scan_form_tags();
        foreach ($form_tags as $tag) {
            if ($tag['name'] === $field_name) {
                return !empty($tag['labels'][0]) ? $tag['labels'][0] : $field_name;
            }
        }
        return $field_name;
    }

    protected function get_field_type($form, $field_name) {
        $form_tags = $form->scan_form_tags();
        foreach ($form_tags as $tag) {
            if ($tag['name'] === $field_name) {
                switch ($tag['type']) {
                    case 'email':
                        return 'email';
                    case 'url':
                        return 'url';
                    case 'tel':
                        return 'tel';
                    case 'textarea':
                        return 'textarea';
                    case 'checkbox':
                    case 'radio':
                        return 'checkbox';
                    case 'file':
                        return 'upload';
                    default:
                        return 'text';
                }
            }
        }
        return 'text';
    }

    public function handle_cf7_submission($contact_form) {
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) return;

        $this->insert_lead_post($contact_form->id(), $submission);
    }

    public function __construct() {
        add_action('wpcf7_mail_sent', array($this, 'handle_cf7_submission'));
    }
}

// Initialize the handler
new Elementor_Leads_CF7_Handler(); 