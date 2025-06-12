<?php
function get_field_label_by_type($data){
	
	if(isset($data['field_label']) && !empty($data['field_label'])){
		return $data['field_label'];
	}
	
	if(isset($data['placeholder']) && !empty($data['placeholder'])){
		return $data['placeholder'];
	}
	
	return __('No Label','elementor-leads');
	
}

function display_value_by_type($data){

	$field_value = $data['value'];
	if(!$field_value){
		echo "-";
		return;
	}
	switch($data['type']):
		case "email":
			$safe_email = esc_html($field_value);
			echo "<a dir='ltr' target='_blank' href='mailto:" . esc_attr($field_value) . "'>" . $safe_email . "</a>";
			break;
		case "tel":
			echo "<span dir='ltr'>" . esc_html($field_value) . "</span>";
			break;
		case "textarea":
			echo nl2br(esc_html($data['value']));
			break;
		case "html":
			echo wp_kses_post($field_value);
			break;
		case "url":
			$safe_url = esc_url($field_value);
			$safe_display = esc_html(urldecode($field_value));
			echo "<a target='_blank' href='$safe_url'>$safe_display</a>";
			break;
		case "acceptance":
			echo '<input type="checkbox" checked disabled style="opacity:1">';
			break;
		case "checkbox":
			$checks = explode(',', $data['value']);
			$count = 0;
			$count_checks = count($checks);
			foreach($checks as $val){
				$count++;
				echo "<span>" . esc_html($val) . "</span>";
				if($count_checks != $count){
					echo "<br>";
				}
			}
			break;
		case "upload":
			$safe_url = esc_url($field_value);
			echo "<a target='_blank' href='" . $safe_url . "'>" . esc_html__('Download File','elementor-leads') . "</a>"; 
			break;
		default:
			echo esc_html($field_value);
	endswitch;

}

add_action('admin_head', 'edisable_new_posts');
function edisable_new_posts() {
  if (isset($_GET['post_type']) && $_GET['post_type'] == 'elementor_lead') {
	  echo '<style type="text/css">
	  .page-title-action { display:none; }
	  </style>';
  }
}
 
add_action( 'admin_menu', 'elementor_leads_register_admin_menu', 205 );
function elementor_leads_register_admin_menu() {
	add_menu_page(
		__( 'Leads Collector', 'elementor-leads' ),
		__( 'Leads Collector', 'elementor-leads' ),
		'publish_pages',
		'edit.php?post_type=elementor_lead',  // Changed to direct URL
		'',  // No callback needed
		'dashicons-list-view',
		100
	);
	
	// Add submenu pages (without 'All leads' which is now the main page)
	add_submenu_page(
		'edit.php?post_type=elementor_lead',  // Changed parent slug
		__('All leads', 'elementor-leads'),
		__('All leads', 'elementor-leads'),
		'manage_options',
		'edit.php?post_type=elementor_lead',  
		''  // 
	);

	// Add submenu pages (without 'All leads' which is now the main page)
	add_submenu_page(
		'edit.php?post_type=elementor_lead',  // Changed parent slug
		__('Leads by form', 'elementor-leads'),
		__('By form', 'elementor-leads'),
		'manage_options',
		'elementor-leads-by-form',
		array(new Lenix_Register_Elementor_Forms(), 'display_forms_in_admin_panel')
	);
	
	add_submenu_page(
		'edit.php?post_type=elementor_lead',  // Changed parent slug
		__('Lead Statuses', 'elementor-leads'),
		__('Statuses', 'elementor-leads'),
		'manage_options',
		'edit-tags.php?taxonomy=lead_status&post_type=elementor_lead'
	);
	
	// Add submenu for Custom Fields that redirects to the Settings page with the "fields" tab.
	add_submenu_page(
		'edit.php?post_type=elementor_lead',
		__('Custom Fields', 'elementor-leads'),
		__('Custom Fields', 'elementor-leads'),
		'manage_options',
		'edit.php?post_type=elementor_lead&page=elementor-leads-settings&tab=fields'
	);
		
	add_submenu_page(
		'edit.php?post_type=elementor_lead',
		__('Settings', 'elementor-leads'),
		__('Settings', 'elementor-leads'),
		'manage_options',
		'elementor-leads-settings',
		'elementor_leads_display_settings_page'
	);

	remove_submenu_page('edit.php?post_type=elementor_lead', 'lenix-custom-fields');
}

function elementor_leads_display_settings_page() {
	// Get current tab
	$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
	
	echo '<div class="wrap">';
	echo '<h1>' . __('Leads Collector Settings', 'elementor-leads') . '</h1>';
	
	// Tabs navigation
	echo '<nav class="nav-tab-wrapper">';
	echo sprintf('<a href="?post_type=elementor_lead&page=elementor-leads-settings&tab=general" class="nav-tab %s">%s</a>',
		$current_tab === 'general' ? 'nav-tab-active' : '',
		__('General Settings', 'elementor-leads')
	);
	echo sprintf('<a href="?post_type=elementor_lead&page=elementor-leads-settings&tab=statuses" class="nav-tab %s">%s</a>',
		$current_tab === 'statuses' ? 'nav-tab-active' : '',
		__('Lead Statuses', 'elementor-leads')
	);
	echo sprintf('<a href="?post_type=elementor_lead&page=elementor-leads-settings&tab=fields" class="nav-tab %s">%s</a>',
		$current_tab === 'fields' ? 'nav-tab-active' : '',
		__('Custom Fields', 'elementor-leads')
	);
	echo '</nav>';

	// Tab content
	echo '<div class="tab-content">';
	switch ($current_tab) {
		case 'general':
			elementor_leads_display_general_settings();
			break;
		case 'statuses':
			elementor_leads_display_statuses_settings();
			break;
		case 'fields':
			if (class_exists('Lenix_Custom_Fields')) {
				$fields_instance = new Lenix_Custom_Fields();
				$fields_instance->render_settings_page();
			}
			break;
	}
	echo '</div>';
	echo '</div>';
}

function elementor_leads_display_general_settings() {
	echo '<div class="general-settings-wrapper">';
	echo '<h2>' . __('General Settings', 'elementor-leads') . '</h2>';
	echo '<p>' . __('Configure general plugin settings here.', 'elementor-leads') . '</p>';
	// Add your general settings form here
	do_action('elementor_leads_general_settings');
	echo '</div>';
}

function elementor_leads_display_statuses_settings() {
	echo '<div class="statuses-settings-wrapper">';
	echo '<h2>' . __('Lead Statuses Management', 'elementor-leads') . '</h2>';
	echo '<p>' . __('Manage your lead statuses here.', 'elementor-leads') . '</p>';
	
	// Redirect to the taxonomy management page
	$taxonomy_url = admin_url('edit-tags.php?taxonomy=lead_status&post_type=elementor_lead');
	echo '<script type="text/javascript">window.location.href = "' . esc_url($taxonomy_url) . '";</script>';
	
	// Add a fallback link in case JavaScript is disabled
	echo '<p><a href="' . esc_url($taxonomy_url) . '" class="button button-primary">' . 
		 __('Manage Lead Statuses', 'elementor-leads') . '</a></p>';
	
	echo '</div>';
}

add_action('load-edit.php', function() {
    if (isset($_GET['post_type']) && $_GET['post_type'] === 'elementor_lead') {
        Lenix_Custom_Fields::check_lead_access();
    }
});

/**
 * Save lead status
 */
function lenix_save_lead_status($post_id) {
    // Make sure we're not in an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // Check if this is a revision
    if (wp_is_post_revision($post_id)) {
        return;
    }
    
    // Check permissions
    $edit_cap = get_option('elementor_leads_edit_role', 'edit_others_posts');
    if (!current_user_can($edit_cap)) {
        return;
    }
    
    // Save lead status if it was submitted
    if (isset($_POST['lead_status'])) {
        $status = sanitize_text_field($_POST['lead_status']);
        update_post_meta($post_id, 'lead_status', $status);
    }
}

// Hook into save_post action
add_action('save_post_elementor_lead', 'lenix_save_lead_status');

/**
 * Redirect helper for the "Custom Fields" submenu.
 */
function lenix_redirect_to_custom_fields() {
    // Build target URL
    $url = admin_url( 'edit.php?post_type=elementor_lead&page=elementor-leads-settings&tab=fields' );
    wp_safe_redirect( $url );
    exit;
}