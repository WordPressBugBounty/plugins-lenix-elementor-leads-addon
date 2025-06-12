<?php
if (!defined('ABSPATH')) exit;

class Lenix_Lead_Status {
    
    public function __construct() {
        add_action('lead_status_add_form_fields', array($this, 'add_status_color_field'));
        add_action('lead_status_edit_form_fields', array($this, 'edit_status_color_field'));
        add_action('created_lead_status', array($this, 'save_status_color'));
        add_action('edited_lead_status', array($this, 'save_status_color'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_color_picker'));
        add_action('manage_elementor_lead_posts_columns', array($this, 'add_status_column'));
        add_action('manage_elementor_lead_posts_custom_column', array($this, 'render_status_column'), 10, 2);
        //add_action('admin_menu', array($this, 'add_status_menu'));
        add_action('admin_notices', array($this, 'add_status_management_link'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_update_lead_status', array($this, 'update_lead_status_ajax'));
    }

    public function enqueue_color_picker($hook) {
        if ('edit-tags.php' !== $hook && 'term.php' !== $hook) {
            return;
        }
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script('wp-color-picker', '
            jQuery(document).ready(function($){
                $(".color-picker").wpColorPicker();
            });
        ');
    }

    public function add_status_color_field() {
        ?>
        <div class="form-field">
            <label for="status_color"><?php _e('Status Color', 'elementor-leads'); ?></label>
            <input type="text" name="status_color" class="color-picker" value="#000000" />
            <p><?php _e('Choose a color for this status', 'elementor-leads'); ?></p>
        </div>
        <?php
    }

    public function edit_status_color_field($term) {
        $color = get_term_meta($term->term_id, 'status_color', true);
        ?>
        <tr class="form-field">
            <th scope="row"><label for="status_color"><?php _e('Status Color', 'elementor-leads'); ?></label></th>
            <td>
                <input type="text" name="status_color" class="color-picker" value="<?php echo esc_attr($color); ?>" />
                <p class="description"><?php _e('Choose a color for this status', 'elementor-leads'); ?></p>
            </td>
        </tr>
        <?php
    }

    public function save_status_color($term_id) {
        if (isset($_POST['status_color'])) {
            update_term_meta($term_id, 'status_color', sanitize_hex_color($_POST['status_color']));
        }
    }

    public function add_status_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns[$key] = $value;
                $new_columns['lead_status'] = __('Status', 'elementor-leads');
            } else {
                $new_columns[$key] = $value;
            }
        }
        return $new_columns;
    }

    public function render_status_column($column, $post_id) {
        if ($column === 'lead_status') {
            // Get current status
            $terms = wp_get_post_terms($post_id, 'lead_status');
            $current_status = !empty($terms) ? $terms[0] : null;
            
            // Get all statuses
            $all_statuses = get_terms(array(
                'taxonomy' => 'lead_status',
                'hide_empty' => false
            ));

            // Display current status with color
            if ($current_status) {
                $color = get_term_meta($current_status->term_id, 'status_color', true);
                echo '<span class="lead-status-badge" style="background-color: ' . esc_attr($color) . '; color: #fff; padding: 3px 8px; border-radius: 3px; margin-bottom: 5px; display: inline-block;">';
                echo esc_html($current_status->name);
                echo '</span>';
            }

            // Add quick edit dropdown
            echo '<select class="quick-status-change" data-post-id="' . esc_attr($post_id) . '">';
            echo '<option value="">' . __('Change Status', 'elementor-leads') . '</option>';
            foreach ($all_statuses as $status) {
                $selected = ($current_status && $current_status->term_id === $status->term_id) ? 'selected' : '';
                echo '<option value="' . esc_attr($status->term_id) . '" ' . $selected . '>';
                echo esc_html($status->name);
                echo '</option>';
            }
            echo '</select>';
        }
    }

    public function enqueue_scripts($hook) {
        if ('edit.php' !== $hook || get_post_type() !== 'elementor_lead') {
            return;
        }

        wp_enqueue_script(
            'lead-status-quick-edit',
            ELEMENTOR_LEADS_URL . 'assets/js/lead-status-quick-edit.js',
            array('jquery'),
            ELEMENTOR_LEADS_VERSION,
            true
        );

        wp_localize_script('lead-status-quick-edit', 'leadStatusVars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lead_status_change')
        ));
    }

    public function add_status_management_link() {
        $screen = get_current_screen();
        if ($screen->id === 'edit-elementor_lead') {
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <?php _e('Manage lead statuses and their colors:', 'elementor-leads'); ?>
                    <a href="<?php echo admin_url('edit-tags.php?taxonomy=lead_status&post_type=elementor_lead'); ?>">
                        <?php _e('Lead Statuses Settings', 'elementor-leads'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * AJAX handler for updating lead status
     */
    public function update_lead_status_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'lead_status_change')) {
            wp_send_json_error('Invalid nonce');
        }

        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }

        // Get and sanitize input
        $post_id = intval($_POST['post_id']);
        $status = sanitize_text_field($_POST['status']);

        // Update the post terms
        $result = wp_set_object_terms($post_id, $status, 'lead_status');

        if (is_wp_error($result)) {
            wp_send_json_error('Failed to update status');
        }

        wp_send_json_success();
    }
}

new Lenix_Lead_Status(); 