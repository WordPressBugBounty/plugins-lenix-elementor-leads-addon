<?php
/*
Plugin Name: Lenix Leads Collector
Plugin URI:  https://lenix.co.il/plugin/lenix-elementor-leads-addon/
Version:     1.9.0
Description: Leads Collector, Collects forms entries from Elementor,Cf7,WPForms and more with export to CSV.
Author:      Lenix
Author URI:  https://lenix.co.il/
Text Domain: elementor-leads
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// constants
define( 'ELEMENTOR_LEADS__FILE__', __FILE__ );
define( 'ELEMENTOR_LEADS_PLUGIN_BASE', plugin_basename( ELEMENTOR_LEADS__FILE__ ) );
define( 'ELEMENTOR_LEADS_URL', plugins_url( '/', ELEMENTOR_LEADS__FILE__ ) );
define( 'ELEMENTOR_LEADS_PATH', plugin_dir_path( ELEMENTOR_LEADS__FILE__ ) );
define( 'ELEMENTOR_LEADS_VERSION', 1.9);


load_plugin_textdomain( 'elementor-leads',false, dirname( plugin_basename( __FILE__ ) ). '/languages'  );
	
require( ELEMENTOR_LEADS_PATH . 'inc/postype-taxonomy.php' );
require( ELEMENTOR_LEADS_PATH . 'inc/meta-boxes.php' );
require( ELEMENTOR_LEADS_PATH . 'inc/functions.php' );	

require( ELEMENTOR_LEADS_PATH . 'inc/class-lenix-elementor-forms.php' );

// Load integrations after all plugins are loaded
function lenix_elementor_leads_load_integrations() {
    // Load Elementor integration
    if (class_exists('\ElementorPro\Plugin') || defined('HELLOPLUS_VERSION')) {
        require(ELEMENTOR_LEADS_PATH . 'inc/elementor-api.php');
    }

    // Load CF7 integration
    if (class_exists('WPCF7')) {
        require(ELEMENTOR_LEADS_PATH . 'inc/cf7-integration.php');
    }

    // Load WPForms integration
    if (class_exists('WPForms')) {
        require(ELEMENTOR_LEADS_PATH . 'inc/wpforms-integration.php');
    }
}
add_action('plugins_loaded', 'lenix_elementor_leads_load_integrations', 20);


add_filter( 'plugin_row_meta', 'lenix_plugin_row_meta', 10, 2 );
function lenix_plugin_row_meta( $links, $file ) {
	if ( !strpos( $file, 'elementor-leads' ) === false ) {
		$leads_link = array(
			'lead_list' => '<a href="'.admin_url().'/admin.php?page=elementor-leads" target="_blank">'.__( 'Forms entries', 'elementor-leads' ).'</a>'
		);
		
		$links = array_merge( $links, $leads_link );
	}
	
	return $links;
}

