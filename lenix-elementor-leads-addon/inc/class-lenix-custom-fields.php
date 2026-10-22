<?php
if (!defined('ABSPATH')) exit;

class Lenix_Custom_Fields {
    private $fields_table;
    private $values_table;
    
    public function __construct() {
        global $wpdb;
        $this->fields_table = $wpdb->prefix . 'lenix_custom_lead_fields';
        $this->values_table = $wpdb->prefix . 'lenix_custom_lead_values';
        
        // Install / upgrade the tables only when needed (avoids SHOW TABLES on every request)
        if (get_option('lenix_leads_db_version') !== '1' && !$this->is_installed()) {
            $this->install();
        }
        if (get_option('lenix_leads_db_version') !== '1') {
            update_option('lenix_leads_db_version', '1', false);
        }
        
        // Add init hook for permission checks
        add_action('init', array($this, 'init_permissions'));
        
        // Lead hooks
        add_action('lenix_after_lead_save', array($this, 'save_lead_custom_fields'));
        add_action('add_meta_boxes', array($this, 'add_custom_fields_meta_box'));
        add_action('add_meta_boxes', array($this, 'add_lead_source_meta_box'));
        
        // Add settings
        add_action('elementor_leads_general_settings', array($this, 'render_lead_tracking_settings'));
        add_action('admin_init', array($this, 'register_lead_tracking_settings'));
        
        // Add tracking code if enabled
        add_action('wp_footer', array($this, 'add_tracking_code'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }

    /**
     * Initialize permission-based hooks
     */
    public function init_permissions() {
        // Get required capabilities
        $view_cap = get_option('elementor_leads_view_role', 'edit_posts');
        $edit_cap = get_option('elementor_leads_edit_role', 'edit_others_posts');
        
        // Admin hooks
        if (is_admin()) {
            if (current_user_can($edit_cap)) {
                add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
                add_action('wp_ajax_save_custom_field', array($this, 'ajax_save_custom_field'));
                add_action('wp_ajax_delete_custom_field', array($this, 'ajax_delete_custom_field'));
                add_action('wp_ajax_update_custom_fields_order', array($this, 'ajax_update_custom_fields_order'));
                add_action('save_post_elementor_lead', array($this, 'save_lead_custom_fields'), 10, 1);
            }
            
            if (current_user_can($view_cap)) {
                add_filter('manage_elementor_lead_posts_columns', array($this, 'add_custom_columns'));
                add_action('manage_elementor_lead_posts_custom_column', array($this, 'render_custom_column'), 10, 2);
                add_action('restrict_manage_posts', array($this, 'add_custom_filters'));
                add_filter('parse_query', array($this, 'filter_leads_by_custom_fields'));
            }
        }
    }

    /**
     * Check if tables are installed and up to date
     */
    private function is_installed() {
        global $wpdb;
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($this->fields_table))) === $this->fields_table;
        if (!$table_exists) {
            return false;
        }
        
        // Check if show_in_list column exists
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$this->fields_table}");
        if (!in_array('show_in_list', $columns)) {
            return false;
        }
        
        return true;
    }

    /**
     * Create or update plugin tables
     */
    public function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Create tables if they don't exist
        $fields_sql = "CREATE TABLE IF NOT EXISTS {$this->fields_table} (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            field_key VARCHAR(255) UNIQUE NOT NULL,
            field_label VARCHAR(255) NOT NULL,
            field_type VARCHAR(50) NOT NULL,
            default_value TEXT NULL,
            is_required BOOLEAN DEFAULT 0,
            show_in_list BOOLEAN DEFAULT 0,
            field_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset_collate;";

        $values_sql = "CREATE TABLE IF NOT EXISTS {$this->values_table} (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            lead_id BIGINT(20) UNSIGNED NOT NULL,
            field_key VARCHAR(255) NOT NULL,
            field_value TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (lead_id),
            FOREIGN KEY (lead_id) REFERENCES {$wpdb->posts}(ID) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($fields_sql);
        dbDelta($values_sql);

        // Check if show_in_list column exists and add it if it doesn't
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$this->fields_table}");
        if (!in_array('show_in_list', $columns)) {
            $wpdb->query("ALTER TABLE {$this->fields_table} ADD COLUMN show_in_list BOOLEAN DEFAULT 0 AFTER is_required");
        }
    }

    /**
     * Check required files
     */

    /**
     * Render the settings page
     */
    public function render_settings_page() {
        include(ELEMENTOR_LEADS_PATH . 'templates/custom-fields/settings.php');
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on the plugin screens
        $screen = get_current_screen();
        if (!$screen || 'elementor_lead' !== $screen->post_type) {
            return;
        }

        wp_enqueue_style(
            'lenix-custom-fields',
            ELEMENTOR_LEADS_URL . 'assets/css/custom-fields.css',
            array(),
            ELEMENTOR_LEADS_VERSION
        );

        wp_enqueue_script(
            'lenix-custom-fields',
            ELEMENTOR_LEADS_URL . 'assets/js/custom-fields.js',
            array('jquery', 'jquery-ui-sortable'),
            ELEMENTOR_LEADS_VERSION,
            true
        );

        wp_localize_script('lenix-custom-fields', 'elementorLeadsVars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lenix_custom_fields'),
            'messages' => array(
                'required_fields' => __('Field Label is required', 'elementor-leads'),
                'confirm_delete' => __('Are you sure you want to delete this field?', 'elementor-leads'),
                'saved' => __('Field saved successfully', 'elementor-leads'),
                'saving' => __('Saving...', 'elementor-leads'),
                'save_field' => __('Save Field', 'elementor-leads'),
                'deleting' => __('Deleting...', 'elementor-leads'),
                'error' => __('Error occurred', 'elementor-leads')
            )
        ));
    }

    /**
     * Get all custom fields
     */
    public function get_fields() {
        global $wpdb;
        
        
        return $wpdb->get_results(
            "SELECT * FROM {$this->fields_table} ORDER BY field_order ASC"
        );
    }

    /**
     * Get field by key
     */
    public function get_field($field_key) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->fields_table} WHERE field_key = %s",
            $field_key
        ));
    }

    /**
     * Save or update custom field
     */
    public function ajax_save_custom_field() {
        check_ajax_referer('lenix_custom_fields', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'elementor-leads'));
        }

        // Get and validate field label
        $field_label = isset($_POST['field_label']) ? sanitize_text_field(wp_unslash($_POST['field_label'])) : '';
        if (empty($field_label)) {
            wp_send_json_error(__('Field label is required', 'elementor-leads'));
        }

        // Update an existing field when its key was posted, otherwise generate a key from the label
        $posted_key = isset($_POST['field_key']) ? sanitize_key(wp_unslash($_POST['field_key'])) : '';
        $existing = $posted_key ? $this->get_field($posted_key) : null;

        if ($existing) {
            $field_key = $existing->field_key;
        } else {
            $field_key = 'field_' . sanitize_key(str_replace(' ', '_', strtolower($field_label)));

            // If field key already exists, append a number
            $counter = 1;
            $original_key = $field_key;
            while ($this->get_field($field_key)) {
                $field_key = $original_key . '_' . $counter;
                $counter++;
            }
        }

        $allowed_types = array('text', 'email', 'number', 'textarea', 'url', 'select', 'checkbox');
        $field_type = isset($_POST['field_type']) ? sanitize_key(wp_unslash($_POST['field_type'])) : 'text';
        if (!in_array($field_type, $allowed_types, true)) {
            $field_type = 'text';
        }

        $field_data = array(
            'field_key' => $field_key,
            'field_label' => $field_label,
            'field_type' => $field_type,
            'default_value' => isset($_POST['default_value']) ? sanitize_textarea_field(wp_unslash($_POST['default_value'])) : '',
            'is_required' => !empty($_POST['is_required']) ? 1 : 0,
            'field_order' => isset($_POST['field_order']) ? intval($_POST['field_order']) : 0
        );

        global $wpdb;
        
        if ($existing) {
            // Update
            $result = $wpdb->update(
                $this->fields_table,
                $field_data,
                array('field_key' => $field_data['field_key'])
            );
        } else {
            // Insert
            $result = $wpdb->insert($this->fields_table, $field_data);
        }

        if ($result === false) {
            wp_send_json_error(sprintf(
                __('Database error: %s', 'elementor-leads'),
                $wpdb->last_error
            ));
        }

        wp_send_json_success();
    }

    /**
     * Delete custom field
     */
    public function ajax_delete_custom_field() {
        check_ajax_referer('lenix_custom_fields', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'elementor-leads'));
        }

        $field_key = isset($_POST['field_key']) ? sanitize_key(wp_unslash($_POST['field_key'])) : '';
        if ($field_key === '') {
            wp_send_json_error(__('Invalid field', 'elementor-leads'));
        }
        
        global $wpdb;
        $result = $wpdb->delete(
            $this->fields_table,
            array('field_key' => $field_key)
        );
        // Remove the stored values of the deleted field as well
        $wpdb->delete($this->values_table, array('field_key' => $field_key));

        if ($result === false) {
            wp_send_json_error(sprintf(
                __('Database error: %s', 'elementor-leads'),
                $wpdb->last_error
            ));
        }

        wp_send_json_success();
    }

    /**
     * Update custom fields order
     */
    public function ajax_update_custom_fields_order() {
        check_ajax_referer('lenix_custom_fields', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'elementor-leads'));
        }

        if (!isset($_POST['order']) || !is_array($_POST['order'])) {
            wp_send_json_error(__('Invalid order data', 'elementor-leads'));
        }

        global $wpdb;
        
        foreach (array_values($_POST['order']) as $index => $item) {
            // Accept a plain list of field keys (ordered) or items with explicit key / order
            if (is_array($item)) {
                $field_key = isset($item['field_key']) ? sanitize_key($item['field_key']) : '';
                $field_order = isset($item['field_order']) ? intval($item['field_order']) : $index;
            } else {
                $field_key = sanitize_key($item);
                $field_order = $index;
            }
            if ($field_key === '') {
                continue;
            }
            
            $wpdb->update(
                $this->fields_table,
                array('field_order' => $field_order),
                array('field_key' => $field_key)
            );
        }

        wp_send_json_success();
    }

    /**
     * Add meta box to lead page
     */
    public function add_custom_fields_meta_box() {
        add_meta_box(
            'lenix_lead_custom_fields',
            __('Custom Fields', 'elementor-leads'),
            array($this, 'render_custom_fields_meta_box'),
            'elementor_lead',
            'normal',
            'default'
        );
    }

    /**
     * Render custom fields in lead page
     */
    public function render_custom_fields_meta_box($post) {
        $fields = $this->get_fields();
        $values = $this->get_lead_values($post->ID);
        
        include(ELEMENTOR_LEADS_PATH . 'templates/custom-fields/meta-box.php');
    }

    /**
     * Get field values for a lead
     */
    public function get_lead_values($lead_id) {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT field_key, field_value FROM {$this->values_table} WHERE lead_id = %d",
            $lead_id
        ), OBJECT_K);

        return wp_list_pluck($results, 'field_value', 'field_key');
    }

    /**
     * Save custom field values for a lead
     */
    public function save_lead_custom_fields($post_id) {
        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check if our custom fields were submitted
        if (!isset($_POST['custom_fields']) || !is_array($_POST['custom_fields'])) {
            return;
        }

        // Only handle leads, never revisions or other post types
        if (wp_is_post_revision($post_id) || 'elementor_lead' !== get_post_type($post_id)) {
            return;
        }

        // Verify the lead edit screen nonce (CSRF protection)
        if (!isset($_POST['elementor_leads_meta_box_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['elementor_leads_meta_box_nonce'])), 'elementor_leads_meta_box_nonce')) {
            return;
        }

        // Get required capability
        $edit_cap = get_option('elementor_leads_edit_role', 'edit_others_posts');
        
        // Check if user has permission to edit leads
        if (!current_user_can($edit_cap) || !current_user_can('edit_post', $post_id)) {
            // Add error message directly to the page
            add_filter('redirect_post_location', function($location) {
                return add_query_arg('lenix_error', 'permission', $location);
            });
            return;
        }

        global $wpdb;
        
        // First, delete all existing values for this lead
        $wpdb->delete(
            $this->values_table,
            array('lead_id' => $post_id),
            array('%d')
        );
        
        // Then insert new values
        foreach (wp_unslash($_POST['custom_fields']) as $field_key => $value) {
            $field_key = sanitize_key($field_key);
            $field = $this->get_field($field_key);
            if (!$field || is_array($value)) continue;

            // Handle checkbox fields
            if ($field->field_type === 'checkbox') {
                $value = !empty($value) ? '1' : '0';
            }

            // Sanitize based on field type
            $sanitized_value = $this->sanitize_field_value($value, $field->field_type);
            
            // Only insert if value is not empty or it's a checkbox
            if ($sanitized_value !== '' || $field->field_type === 'checkbox') {
                $wpdb->insert(
                    $this->values_table,
                    array(
                        'lead_id' => $post_id,
                        'field_key' => $field_key,
                        'field_value' => $sanitized_value
                    ),
                    array('%d', '%s', '%s')
                );
            }
        }
    }

    /**
     * Sanitize field value based on type
     */
    private function sanitize_field_value($value, $type) {
        switch ($type) {
            case 'email':
                return sanitize_email($value);
            case 'url':
                return esc_url_raw($value);
            case 'number':
                return floatval($value);
            case 'textarea':
                return sanitize_textarea_field($value);
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Add custom field columns to leads list
     */
    public function add_custom_columns($columns) {
        //add one column with name "custom fields"
        $columns['custom_fields'] = __('Custom Fields', 'elementor-leads');

       /* $fields = $this->get_fields();
        
        foreach ($fields as $field) {
            if ($field->show_in_list) {
                $key = 'custom_field_' . $field->field_key;
                $columns[$key] = $field->field_label;
            }
        }*/
        
        return $columns;
    }

    /**
     * Render custom field values in leads list
     */
    public function render_custom_column($column, $post_id) {
        if ($column === 'custom_fields') {
            // קבלת כל הערכים של הליד
            $values = $this->get_lead_values($post_id);
            
            // אם אין ערכים, נצא מהפונקציה
            if (empty($values)) {
                echo '-';
                return;
            }

            echo '<table class="wp-list-table widefat fixed striped lenix-leads-fields">';
            echo '<tbody>';
            foreach ($values as $field_key => $value) {
                // קבלת הגדרות השדה
                $field = $this->get_field($field_key);
                if (!$field) continue;

                echo '<tr>';
                echo '<th>' . esc_html($field->field_label) . '</th>';
                echo '<td>';
                
                // טיפול בסוגי שדות שונים
                switch ($field->field_type) {
                    case 'checkbox':
                        echo $value ? '✓' : '✗';
                        break;
                    case 'textarea':
                        echo !empty($value) ? esc_html(wp_trim_words($value, 5)) : '-'; // מציג רק 5 מילים
                        break;
                    case 'email':
                        if (!empty($value)) {
                            echo '<a dir="ltr" target="_blank" href="mailto:' . esc_attr($value) . '">' . esc_html($value) . '</a>';
                        } else {
                            echo '-';
                        }
                        break;
                    case 'url':
                        if (!empty($value)) {
                            echo '<a href="' . esc_url($value) . '" target="_blank">' . esc_html($value) . '</a>';
                        } else {
                            echo '-';
                        }
                        break;
                    default:
                        echo !empty($value) ? esc_html($value) : '-';
                }
                echo '</td></tr>';
            }
            echo '</tbody></table>';

            // הוספת סגנון CSS מינימלי לתיקונים קטנים אם נדרש
            ?>
            <style>
                .lenix-leads-fields {
                    margin: 0;
                }
                .lenix-leads-fields th {
                    font-weight: 500;
                    width: 40%;
                }
                /* Leads table */
                td.lead_status.column-lead_status {
                    display: grid;
                }

                td.lead_status.column-lead_status > * {max-width: max-content;}
            </style>
            <?php
        } else if (strpos($column, 'custom_field_') === 0 && 0) {
            // שמירה על הפונקציונליות הקיימת לעמודות בודדות
            $field_key = str_replace('custom_field_', '', $column);
            $values = $this->get_lead_values($post_id);
            
            if (isset($values[$field_key])) {
                $field = $this->get_field($field_key);
                $value = $values[$field_key];
                
                switch ($field->field_type) {
                    case 'checkbox':
                        echo $value ? '✓' : '✗';
                        break;
                    case 'textarea':
                        echo esc_html(wp_trim_words($value, 10));
                        break;
                    default:
                        echo esc_html($value);
                }
            }
        }
    }

    /**
     * Add custom field filters to leads list
     */
    public function add_custom_filters() {
        global $typenow;
        
        if ($typenow !== 'elementor_lead') {
            return;
        }
        
        $fields = $this->get_fields();
        foreach ($fields as $field) {
            if ($field->show_in_list && $field->field_type === 'select') {
                $current = isset($_GET['custom_field_' . $field->field_key]) ? sanitize_text_field(wp_unslash($_GET['custom_field_' . $field->field_key])) : '';
                
                echo '<select name="custom_field_' . esc_attr($field->field_key) . '">';
                echo '<option value="">' . esc_html(sprintf(__('All %s', 'elementor-leads'), $field->field_label)) . '</option>';
                
                $options = explode("\n", $field->default_value);
                foreach ($options as $option) {
                    $option = trim($option);
                    echo '<option value="' . esc_attr($option) . '" ' . selected($current, $option, false) . '>' . esc_html($option) . '</option>';
                }
                
                echo '</select>';
            }
        }
    }

    /**
     * Filter leads by custom field values
     */
    public function filter_leads_by_custom_fields($query) {
        global $pagenow, $typenow;
        
        if ($pagenow !== 'edit.php' || $typenow !== 'elementor_lead' || !is_admin()) {
            return;
        }
        
        $meta_query = array();
        
        foreach ($_GET as $key => $value) {
            if (strpos($key, 'custom_field_') === 0 && $value && is_scalar($value)) {
                $field_key = sanitize_key(substr($key, strlen('custom_field_')));

                // Only filter by fields that were defined as custom fields - never by arbitrary meta keys
                if (!$this->get_field($field_key)) {
                    continue;
                }
                
                $meta_query[] = array(
                    'key' => $field_key,
                    'value' => sanitize_text_field(wp_unslash($value)),
                    'compare' => '='
                );
            }
        }
        
        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }
    }

    /**
     * Add lead source meta box
     */
    public function add_lead_source_meta_box() {
        add_meta_box(
            'lenix_lead_source',
            __('Lead Source', 'elementor-leads'),
            array($this, 'render_lead_source_meta_box'),
            'elementor_lead',
            'side',
            'high'
        );
    }

    /**
     * Render lead source meta box
     */
    public function render_lead_source_meta_box($post) {
        // Get all source data from single meta field
        $source_data = get_post_meta($post->ID, '_lenix_lead_source_data', true);
        
        $source_fields = array(
            'utm_source' => __('Source', 'elementor-leads'),
            'utm_medium' => __('Medium', 'elementor-leads'),
            'utm_campaign' => __('Campaign', 'elementor-leads'),
            'utm_term' => __('Term', 'elementor-leads'),
            'utm_content' => __('Content', 'elementor-leads'),
            'referrer' => __('Referrer', 'elementor-leads'),
            //'full_url' => __('Landing Page', 'elementor-leads'),
            'ip' => __('IP Address', 'elementor-leads'),
            'timestamp' => __('Timestamp', 'elementor-leads'),
            'user_agent' => __('User Agent', 'elementor-leads'),
            'first_visit_time' => __('First visit time', 'elementor-leads'),
            'landing_page' => __('First visit landing page', 'elementor-leads'),
            'landing_page_title' => __('First visit landing page title', 'elementor-leads'),
            'initial_referrer' => __('Initial referrer', 'elementor-leads')
        );
        
        echo '<div class="lead-source-info">';
        
        if (is_array($source_data)) {
            foreach ($source_fields as $key => $label) {
                if (!empty($source_data[$key])) {
                    echo '<div class="lead-source-field">';
                    echo '<strong>' . esc_html($label) . ':</strong> ';
                    
                    // Special formatting for certain fields
                    switch ($key) {
                        case 'landing_page':
                        case 'initial_referrer':
                            echo esc_html($source_data[$key]);
                            break;
                            
                        case 'referrer':
                            $parsed_url = wp_parse_url($source_data[$key]);
                            echo '<a href="' . esc_url($source_data[$key]) . '" target="_blank">' . 
                                 esc_html($source_data[$key]) . '</a>';
                            break;
                            
                        default:
                            if (filter_var($source_data[$key], FILTER_VALIDATE_URL)) {
                                echo '<a href="' . esc_url($source_data[$key]) . '" target="_blank">' . 
                                     esc_html($source_data[$key]) . '</a>';
                            } else {
                                echo esc_html($source_data[$key]);
                            }
                    }
                    echo '</div>';
                }
            }
        }
        
        // If no data found, show a message
        if (!is_array($source_data) || empty(array_filter($source_data))) {
            echo '<p class="no-source-data">' . esc_html__('No source data available', 'elementor-leads') . '</p>';
        }
        echo '<span class="lenix-lead-source-field-label">*Lead Source information may not always be accurate. The system attempts to gather data from URLs, referrers, POST data and cookies, but accuracy depends on data availability and expiration.</span>';
        echo '</div>';
        
        // Add some styling
        ?>
        <style>
            .lead-source-info {
                margin: -6px -12px -12px;
                padding: 6px 12px;
            }
            
            .lead-source-field {
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }
            
            .lead-source-field:last-child {
                border-bottom: none;
            }
            
            .lead-source-field strong {
                display: block;
                margin-bottom: 2px;
                color: #666;
            }
            
            .lead-source-field a {
                text-decoration: none;
            }
            
            .lead-source-field a:hover {
                text-decoration: underline;
            }
            
            .no-source-data {
                color: #666;
                font-style: italic;
                margin: 0;
                padding: 8px 0;
            }
        </style>
        <?php
    }

    /**
     * Register lead tracking settings
     */
    public function register_lead_tracking_settings() {
        register_setting('elementor_leads_options', 'elementor_leads_retention_days', array(
            'sanitize_callback' => 'absint',
        ));
        register_setting('elementor_leads_options', 'elementor_leads_enable_tracking', array(
            'sanitize_callback' => 'absint',
        ));
        
        // Add new settings for user roles
        register_setting('elementor_leads_options', 'elementor_leads_view_role', array(
            'sanitize_callback' => function ($value) {
                $allowed = array('read', 'edit_posts', 'publish_posts', 'edit_others_posts', 'manage_options');
                return in_array($value, $allowed, true) ? $value : 'edit_posts';
            },
        ));
        register_setting('elementor_leads_options', 'elementor_leads_edit_role', array(
            'sanitize_callback' => function ($value) {
                $allowed = array('edit_posts', 'publish_posts', 'edit_others_posts', 'manage_options');
                return in_array($value, $allowed, true) ? $value : 'edit_others_posts';
            },
        ));
        
        add_settings_section(
            'elementor_leads_tracking_section',
            __('Lead Tracking Settings', 'elementor-leads'),
            array($this, 'render_tracking_section_description'),
            'elementor_leads_settings'
        );
        
        add_settings_section(
            'elementor_leads_permissions_section',
            __('User Permissions', 'elementor-leads'),
            array($this, 'render_permissions_section_description'),
            'elementor_leads_settings'
        );
        
        // Add tracking field
        add_settings_field(
            'elementor_leads_enable_tracking',
            __('Enable Lead Tracking', 'elementor-leads'),
            array($this, 'render_tracking_field'),
            'elementor_leads_settings',
            'elementor_leads_tracking_section'
        );
        
        add_settings_field(
            'elementor_leads_retention_days',
            __('Delete old leads', 'elementor-leads'),
            array($this, 'render_retention_field'),
            'elementor_leads_settings',
            'elementor_leads_tracking_section'
        );

        // Add role fields
        add_settings_field(
            'elementor_leads_view_role',
            __('Who can view leads', 'elementor-leads'),
            array($this, 'render_view_role_field'),
            'elementor_leads_settings',
            'elementor_leads_permissions_section'
        );
        
        add_settings_field(
            'elementor_leads_edit_role',
            __('Who can edit leads', 'elementor-leads'),
            array($this, 'render_edit_role_field'),
            'elementor_leads_settings',
            'elementor_leads_permissions_section'
        );
    }

    /**
     * Render tracking section description
     */
    public function render_tracking_section_description() {
        ?>
        <p>
            <?php _e('Enable cross-page lead source tracking using cookies. This will help track the original source of leads even if they navigate through multiple pages before submitting a form.', 'elementor-leads'); ?>
        </p>
        <p>
            <?php _e('The tracking code will capture:', 'elementor-leads'); ?>
        </p>
        <ul style="list-style-type: disc; margin-left: 20px;">
            <li><?php _e('UTM parameters (source, medium, campaign, term, content)', 'elementor-leads'); ?></li>
            <li><?php _e('Referrer URL', 'elementor-leads'); ?></li>
            <li><?php _e('First visit timestamp', 'elementor-leads'); ?></li>
        </ul>
        <p>
            <?php _e('Data is stored in a cookie for 30 days.', 'elementor-leads'); ?>
        </p>
        <?php
    }

    /**
     * Render tracking enable/disable field
     */
    public function render_tracking_field() {
        $enabled = get_option('elementor_leads_enable_tracking', true);
        ?>
        <label>
            <input type="checkbox" name="elementor_leads_enable_tracking" value="1" <?php checked($enabled, 1); ?> />
            <?php _e('Enable lead source tracking across pages', 'elementor-leads'); ?>
        </label>
        
        <p class="description">
            <?php _e('When enabled, this will add a small JavaScript code to all pages to track lead sources using cookies.', 'elementor-leads'); ?>
        </p>
        
        <?php
    }

    /**
     * Render the leads retention field
     */
    public function render_retention_field() {
        $days = absint(get_option('elementor_leads_retention_days', 0));
        ?>
        <input type="number" min="0" step="1" class="small-text" name="elementor_leads_retention_days" value="<?php echo esc_attr($days); ?>" />
        <?php esc_html_e('days', 'elementor-leads'); ?>
        <p class="description">
            <?php esc_html_e('Automatically and permanently delete leads older than this number of days (checked daily). Set 0 to keep leads forever.', 'elementor-leads'); ?>
        </p>
        <?php
    }

    /**
     * Add tracking code to footer if enabled
     */
    public function add_tracking_code() {
        if (!get_option('elementor_leads_enable_tracking', true)) {
            return;
        }
        
        ?>
        <script type="text/javascript">
            <?php if (current_user_can('administrator')): ?>
            // Note: This comment is only visible to site administrators
            // Lead source tracking code by Lenix
            // you can disable this by setting page_tracking_code_enabled to false in

            <?php endif; ?>

            (function() {
                // First, track UTM parameters
                function getQueryParam(name) {
                    const urlParams = new URLSearchParams(window.location.search);
                    return urlParams.get(name);
                }

                function compressValue(value) {
                    if (!value) return '';
                    try {
                        const decodedValue = decodeURIComponent(value);
                        return decodedValue;
                    } catch (e) {
                        return value;
                    }
                }

                function saveLeadSource() {
                    // Get UTM source first
                    const currentUtmSource = compressValue(getQueryParam('utm_source'));
                    
                    // Only proceed if we have a source
                    if (!currentUtmSource) {
                        return;
                    }

                    const existingCookie = document.cookie.split('; ').find(c => c.startsWith('lenix_utms='));
                    if (existingCookie) {
                        try {
                            const cookieData = JSON.parse(decodeURIComponent(existingCookie.substring('lenix_utms='.length)));
                            // Same utm_source as the saved one - nothing to update
                            if (cookieData.lenix_utm_source === currentUtmSource) {
                                return;
                            }
                        } catch (e) {
                            // Unreadable cookie - overwrite it below
                        }
                    }

                    // Get UTM params without compression
                    const data = {
                        lenix_utm_source: currentUtmSource,
                        lenix_utm_medium: compressValue(getQueryParam('utm_medium')),
                        lenix_utm_campaign: compressValue(getQueryParam('utm_campaign')),
                        lenix_utm_term: compressValue(getQueryParam('utm_term')),
                        lenix_utm_content: compressValue(getQueryParam('utm_content')),
                        lenix_referrer: compressValue(document.referrer),
                        lenix_d: Math.floor(Date.now() / 1000)
                    };

                    // Remove empty values
                    Object.keys(data).forEach(key => {
                        if (!data[key]) delete data[key];
                    });

                    try {
                        const cookieValue = encodeURIComponent(JSON.stringify(data));
                        document.cookie = `lenix_utms=${cookieValue}; path=/; max-age=2592000; SameSite=Lax`;
                    } catch (e) {
                    }
                }

                // New function to track first landing page
                function trackFirstVisit() {
                    // Check if first visit is already tracked
                    // add initial_referrer
                    if (document.cookie.includes('lenix_first_visit=')) {
                        return;
                    }

                    const firstVisitData = {
                        landing_page: window.location.href,
                        landing_page_title: document.title,
                        first_visit_time: Math.floor(Date.now() / 1000),
                        initial_referrer: document.referrer ? document.referrer : 'Direct'
                    };

                    try {
                        const cookieValue = encodeURIComponent(JSON.stringify(firstVisitData));
                        // Set cookie to expire in 30 days
                        document.cookie = `lenix_first_visit=${cookieValue}; path=/; max-age=2592000; SameSite=Lax`;
                    } catch (e) {
                        console.error('Error saving first visit data:', e);
                    }
                }

                // Run both tracking functions
                saveLeadSource();
                trackFirstVisit();
            })();
        </script>
        <?php
    }

    /**
     * Render lead tracking settings form
     */
    public function render_lead_tracking_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Save settings if form was submitted
        if (isset($_POST['elementor_leads_settings_nonce']) && 
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['elementor_leads_settings_nonce'])), 'elementor_leads_settings')) {
            
            // Save tracking setting
            update_option('elementor_leads_enable_tracking', 
                isset($_POST['elementor_leads_enable_tracking']) ? 1 : 0
            );
            
            // Save role settings
            update_option('elementor_leads_retention_days',
                isset($_POST['elementor_leads_retention_days']) ? absint($_POST['elementor_leads_retention_days']) : 0
            );

            // Only known capabilities are accepted
            $view_caps = array('read', 'edit_posts', 'publish_posts', 'edit_others_posts', 'manage_options');
            $edit_caps = array('edit_posts', 'publish_posts', 'edit_others_posts', 'manage_options');

            if (isset($_POST['elementor_leads_view_role'])) {
                $view_role = sanitize_text_field(wp_unslash($_POST['elementor_leads_view_role']));
                if (in_array($view_role, $view_caps, true)) {
                    update_option('elementor_leads_view_role', $view_role);
                }
            }
            
            if (isset($_POST['elementor_leads_edit_role'])) {
                $edit_role = sanitize_text_field(wp_unslash($_POST['elementor_leads_edit_role']));
                if (in_array($edit_role, $edit_caps, true)) {
                    update_option('elementor_leads_edit_role', $edit_role);
                }
            }
            
            echo '<div class="notice notice-success"><p>' . 
                 esc_html__('Settings saved successfully.', 'elementor-leads') . 
                 '</p></div>';
                 
            // Refresh page (as a GET, without re-submitting the form) to update permissions
            echo '<script>window.location.replace(window.location.href);</script>';
        }
        
        ?>
        <form method="post" action="">
            <?php
            settings_fields('elementor_leads_options');
            do_settings_sections('elementor_leads_settings');
            
            wp_nonce_field('elementor_leads_settings', 'elementor_leads_settings_nonce');
            
            submit_button(__('Save Settings', 'elementor-leads'));
            ?>
        </form>
        
        <style>
            .elementor_leads_tracking_section {
                margin-top: 20px;
                max-width: 800px;
            }
            
            .tracking-code-preview {
                margin-top: 20px;
                background: #f5f5f5;
                padding: 15px;
                border-radius: 4px;
            }
            
            .tracking-code-preview pre {
                margin: 0;
                white-space: pre-wrap;
            }
        </style>
        <?php
    }

    /**
     * Render permissions section description
     */
    public function render_permissions_section_description() {
        echo '<p>' . esc_html__('Configure which user roles can view and edit leads.', 'elementor-leads') . '</p>';
    }

    /**
     * Render view role field
     */
    public function render_view_role_field() {
        $current_role = get_option('elementor_leads_view_role', 'edit_posts');
        $roles = array(
            'read' => __('Subscribers', 'elementor-leads'),
            'edit_posts' => __('Contributors', 'elementor-leads'),
            'publish_posts' => __('Authors', 'elementor-leads'),
            'edit_others_posts' => __('Editors', 'elementor-leads'),
            'manage_options' => __('Administrators', 'elementor-leads')
        );
        
        echo '<select name="elementor_leads_view_role">';
        foreach ($roles as $capability => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($capability),
                selected($current_role, $capability, false),
                esc_html($label)
            );
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Users with this role and above will be able to view leads.', 'elementor-leads') . '</p>';
    }

    /**
     * Render edit role field
     */
    public function render_edit_role_field() {
        $current_role = get_option('elementor_leads_edit_role', 'edit_others_posts');
        $roles = array(
            'edit_posts' => __('Contributors', 'elementor-leads'),
            'publish_posts' => __('Authors', 'elementor-leads'),
            'edit_others_posts' => __('Editors', 'elementor-leads'),
            'manage_options' => __('Administrators', 'elementor-leads')
        );
        
        echo '<select name="elementor_leads_edit_role">';
        foreach ($roles as $capability => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($capability),
                selected($current_role, $capability, false),
                esc_html($label)
            );
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Users with this role and above will be able to edit leads.', 'elementor-leads') . '</p>';
    }

    /**
     * Check if user has required capability
     */
    public static function check_lead_access() {
        $view_cap = get_option('elementor_leads_view_role', 'edit_posts');
        
        if (!current_user_can($view_cap)) {
            wp_die(__('You do not have permission to access this page.', 'elementor-leads'));
        }
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        if (isset($_GET['lenix_error'])) {
            $error_type = $_GET['lenix_error'];
            
            if ($error_type === 'permission') {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php _e('You do not have permission to edit leads or send responses. Please contact your administrator for full access.', 'elementor-leads'); ?></p>
                </div>
                <?php
            } elseif ($error_type === 'empty_content') {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php _e('Response content cannot be empty.', 'elementor-leads'); ?></p>
                </div>
                <?php
            }
        }
        
        // Display success message for response
        if (isset($_GET['message']) && $_GET['message'] === 'response_added') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Response added successfully.', 'elementor-leads'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Render field input
     */
    public function render_field_input($field, $value) {
        switch ($field->field_type):
            case 'textarea':
                ?>
                <textarea
                    id="custom_field_<?php echo esc_attr($field->field_key); ?>"
                    name="custom_fields[<?php echo esc_attr($field->field_key); ?>]"
                    rows="3"
                    <?php echo ($field->is_required == 1) ? 'required' : ''; ?>
                ><?php echo esc_textarea($value); ?></textarea>
                <?php
                break;

            case 'select':
                $options = explode("\n", $field->default_value);
                ?>
                <select
                    id="custom_field_<?php echo esc_attr($field->field_key); ?>"
                    name="custom_fields[<?php echo esc_attr($field->field_key); ?>]"
                    <?php echo ($field->is_required == 1) ? 'required' : ''; ?>
                >
                    <option value=""><?php _e('-- Select --', 'elementor-leads'); ?></option>
                    <?php foreach ($options as $option): ?>
                        <option value="<?php echo esc_attr(trim($option)); ?>" <?php selected($value, trim($option)); ?>>
                            <?php echo esc_html(trim($option)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;

            case 'checkbox':
                ?>
                <input
                    type="checkbox"
                    id="custom_field_<?php echo esc_attr($field->field_key); ?>"
                    name="custom_fields[<?php echo esc_attr($field->field_key); ?>]"
                    value="1"
                    <?php checked($value, '1'); ?>
                    <?php echo ($field->is_required == 1) ? 'required' : ''; ?>
                >
                <?php
                break;

            default:
                ?>
                <input
                    type="<?php echo esc_attr($field->field_type); ?>"
                    id="custom_field_<?php echo esc_attr($field->field_key); ?>"
                    name="custom_fields[<?php echo esc_attr($field->field_key); ?>]"
                    value="<?php echo esc_attr($value); ?>"
                    <?php echo ($field->is_required == 1) ? 'required' : ''; ?>
                >
        <?php endswitch;
    }
}

new Lenix_Custom_Fields(); 