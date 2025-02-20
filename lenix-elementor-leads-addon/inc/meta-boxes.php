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
		'elementor-leads',
		'elementor_leads_display_settings_page',
		'dashicons-list-view',
		100
	);
}

function elementor_leads_display_settings_page(){
	
	echo '<div class="wrap">';
		echo '<h2>'.__( 'Leads Collector', 'elementor-leads' ).'</h2>';	
  
  		$link = admin_url()."edit.php?post_type=elementor_lead";
		echo "<a class='button button-primary' style='margin: 20px 0;' href='$link#posts-filter'>".__( 'See as Wordpress Posts list', 'elementor-leads' )."</a>";
  
		do_action('lenix_elementor_leads_admin_options_page_section');	
	echo '</div>';

	
}