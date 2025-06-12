<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function lenix_elementor_leads_register_post_type() {
	
	// Lead
	$labels = array(
		'name'                  => _x( 'Leads', 'Post Type General Name', 'elementor-leads' ),
		'singular_name'         => _x( 'Lead', 'Post Type Singular Name', 'elementor-leads' ),
		'menu_name'             => __( 'Leads', 'elementor-leads' ),
		'name_admin_bar'        => __( 'Leads', 'elementor-leads' ),
		'archives'              => __( 'Lead Archives', 'elementor-leads' ),
		'attributes'            => __( 'Lead Attributes', 'elementor-leads' ),
		'parent_item_colon'     => __( 'Parent Lead:', 'elementor-leads' ),
		'all_items'             => __( 'All Leads', 'elementor-leads' ),
		'add_new_item'          => __( 'Add New Lead', 'elementor-leads' ),
		'add_new'               => __( 'Add New Lead', 'elementor-leads' ),
		'new_item'              => __( 'New Lead', 'elementor-leads' ),
		'edit_item'             => __( 'Edit Lead', 'elementor-leads' ),
		'update_item'           => __( 'Update Lead', 'elementor-leads' ),
		'view_item'             => __( 'View Lead', 'elementor-leads' ),
		'view_items'            => __( 'View Leads', 'elementor-leads' ),
		'search_items'          => __( 'Search Lead', 'elementor-leads' ),
		'not_found'             => __( 'No Leads', 'elementor-leads' ),
		'not_found_in_trash'    => __( 'No Leads found in Trash', 'elementor-leads' ),
		'featured_image'        => __( 'Featured Image', 'elementor-leads' ),
		'set_featured_image'    => __( 'Set featured image', 'elementor-leads' ),
		'remove_featured_image' => __( 'Remove featured image', 'elementor-leads' ),
		'use_featured_image'    => __( 'Use as featured image', 'elementor-leads' ),
		'insert_into_item'      => __( 'Insert into Lead', 'elementor-leads' ),
		'uploaded_to_this_item' => __( 'Uploaded to this Lead', 'elementor-leads' ),
		'items_list'            => __( 'Leads list', 'elementor-leads' ),
		'items_list_navigation' => __( 'Leads list navigation', 'elementor-leads' ),
		'filter_items_list'     => __( 'Filter Leads list', 'elementor-leads' ),
	);
	$args = array(
		'label'                 => __( 'Lead', 'elementor-leads' ),
		'description'           => __( 'leads from forms', 'elementor-leads' ),
		'labels'                => $labels,
		'supports'              => array('title',),
		'hierarchical'          => false,
		'public'                => false,
		'show_ui'               => true,
		'show_in_menu'          => false,
		'menu_position'         => 100,
		'menu_icon'             => 'dashicons-id-alt',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => false,
		'can_export'            => true,
		'has_archive'           => false,		
		'exclude_from_search'   => true,
		'publicly_queryable'    => false,
		'capability_type'       => 'page',
		'show_in_rest'          => false,
	);
	register_post_type( 'elementor_lead', $args );
	
}

add_action( 'init', 'lenix_elementor_leads_register_post_type' );

// Add after the post type registration
function lenix_register_lead_status_taxonomy() {
	$labels = array(
		'name'              => _x('Lead Statuses', 'taxonomy general name', 'elementor-leads'),
		'singular_name'     => _x('Lead Status', 'taxonomy singular name', 'elementor-leads'),
		'search_items'      => __('Search Statuses', 'elementor-leads'),
		'all_items'         => __('All Statuses', 'elementor-leads'),
		'edit_item'         => __('Edit Status', 'elementor-leads'),
		'update_item'       => __('Update Status', 'elementor-leads'),
		'add_new_item'      => __('Add New Status', 'elementor-leads'),
		'new_item_name'     => __('New Status Name', 'elementor-leads'),
		'menu_name'         => __('Statuses', 'elementor-leads'),
	);

	register_taxonomy(
		'lead_status',
		'elementor_lead',
		array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array('slug' => 'lead-status'),
		)
	);

	// Add default statuses if they don't exist
	$default_statuses = array(
		'new' => array(
			'name' => __('New', 'elementor-leads'),
			'color' => '#e44f4f'
		),
		'in-progress' => array(
			'name' => __('In Progress', 'elementor-leads'),
			'color' => '#f1c40f'
		),
		'completed' => array(
			'name' => __('Completed', 'elementor-leads'),
			'color' => '#2ecc71'
		),
		'spam' => array(
			'name' => __('Spam', 'elementor-leads'),
			'color' => '#95a5a6'
		)
	);

	foreach ($default_statuses as $slug => $status) {
		if (!term_exists($slug, 'lead_status')) {
			$term = wp_insert_term($status['name'], 'lead_status', array('slug' => $slug));
			if (!is_wp_error($term)) {
				update_term_meta($term['term_id'], 'status_color', $status['color']);
			}
		}
	}
}
add_action('init', 'lenix_register_lead_status_taxonomy');