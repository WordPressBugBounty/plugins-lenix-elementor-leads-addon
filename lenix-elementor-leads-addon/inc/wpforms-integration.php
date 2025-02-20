<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly


class Lenix_Elementor_Leads_WPForms_Handler {
    
    protected function insert_lead_post($form_id, $fields, $entry_id) {
        $form = wpforms()->form->get($form_id);
        if (!$form) return;

        $form_data = wpforms_decode($form->post_content);
        
        // Process form fields - simplified structure
        $form_fields = array();
        foreach ($fields as $field_id => $field_value) {
            $field = $form_data['fields'][$field_id] ?? null;
            if (!$field) continue;

            // Special handling for name fields
            if ($field['type'] === 'name') {
                // If it's an array, properly format the name
                if (is_array($field_value)) {
                    if (isset($field_value['value'])) {
                        $field_value = $field_value['value'];
                    } else {
                        $name_parts = array_filter($field_value); // Remove empty values
                        $field_value = implode(' ', $name_parts);
                    }
                }
            }
            // Handle other array values
            elseif (is_array($field_value)) {
                $field_value = isset($field_value['value']) ? $field_value['value'] : implode(', ', array_filter($field_value));
            }
            
            // Store only essential data
            $form_fields[$field_id] = array(
                'title' => $field['label'],
                'value' => $field_value,
                'type' => $this->convert_wpforms_field_type($field['type'])
            );
        }

        // Prepare meta data
        $meta = array(
            'lead_data' => wp_slash(wp_json_encode($form_fields)),
            'form_slug' => 'wpf_' . $form_id,
            'form_type' => 'wpforms',
            'post_id' => $form_id,
            'lead_version' => ELEMENTOR_LEADS_VERSION,
            'wpforms_entry_id' => $entry_id
        );

        // Create the lead post
        $lead_id = wp_insert_post(array(
            'post_type' => 'elementor_lead',
            'post_status' => 'publish',
            'post_title' => __('Lead #', 'elementor-leads'),
            'meta_input' => $meta
        ));

        if ($lead_id) {
            wp_update_post(array(
                'ID' => $lead_id,
                'post_title' => __('Lead #', 'elementor-leads') . $lead_id
            ));
        }

        return $lead_id;
    }

    protected function convert_wpforms_field_type($wpforms_type) {
        $type_map = array(
            'email' => 'email',
            'url' => 'url',
            'phone' => 'tel',
            'textarea' => 'textarea',
            'checkbox' => 'checkbox',
            'radio' => 'checkbox',
            'file-upload' => 'upload',
            'name' => 'text',
            'number' => 'text',
            'select' => 'text'
        );
        
        return isset($type_map[$wpforms_type]) ? $type_map[$wpforms_type] : 'text';
    }

    public function handle_wpforms_submission($fields, $entry, $form_data) {
        $this->insert_lead_post($form_data['id'], $fields, $entry['id']);
    }

    public function __construct() {
        if (!class_exists('WPForms')) {
            return;
        }
        
        add_action('wpforms_process_complete', array($this, 'handle_wpforms_submission'), 10, 3);
    }
}

// Initialize the handler
new Lenix_Elementor_Leads_WPForms_Handler();
