<?php
class Lenix_Register_Elementor_Forms {
	
	public $forms;
	public $cf7_forms;
	public $wpforms_forms;
	
	private function get_froms(){
		
        if(!is_null($this->forms)){
        	return array_merge($this->forms, $this->get_cf7_forms(), $this->get_wpforms_forms());
        }
      
      	$this->forms = array();
      
		global $wpdb;
		$sql_query = "SELECT *  FROM `{$wpdb->prefix}postmeta`
		WHERE `meta_key` LIKE '_elementor_data'
		AND (`meta_value` LIKE '%\"widgetType\":\"form\"%' 
		     OR `meta_value` LIKE '%\"widgetType\":\"ehp-form\"%')
		AND `post_id` IN (
			SELECT `id` FROM `{$wpdb->prefix}posts`
			WHERE `post_status` IN ('publish','draft','private')
		)";

		$results = $wpdb->get_results($sql_query);
		
		if (!count($results)){
			return $this->get_froms();
		}
		
		foreach($results as $result){
			$post_id = $result->post_id;
			$data = $result->meta_value;
			$json = json_decode($data,true);
			if($json){
				foreach($json as $j){
					$this->find_form_element($j,$post_id);
				}
			}
		}
      	return array_merge($this->forms, $this->get_cf7_forms(), $this->get_wpforms_forms());
	}
	
	private function get_cf7_forms() {
		if (!class_exists('WPCF7')) {
			return array();
		}

		if (!is_null($this->cf7_forms)) {
			return $this->cf7_forms;
		}

		$this->cf7_forms = array();
		$cf7_forms = WPCF7_ContactForm::find();

		foreach ($cf7_forms as $form) {
			$form_data = array(
				'settings' => array(
					'form_name' => $form->title(),
					'form_fields' => $this->get_cf7_form_fields($form),
					'email_to' => $this->get_cf7_email_recipients($form)
				),
				'id' => 'cf7_' . $form->id(),
				'widgetType' => 'cf7-form'
			);

			$this->cf7_forms[] = array(
				'post_id' => $form->id(),
				'form_data' => $form_data,
				'form_type' => 'cf7'
			);
		}

		return $this->cf7_forms;
	}

	private function get_cf7_form_fields($form) {
		$fields = array();
		$form_tags = $form->scan_form_tags();
		
		foreach ($form_tags as $tag) {
			if (empty($tag['name'])) continue;
			
			$fields[] = array(
				'field_type' => $this->convert_cf7_field_type($tag['type']),
				'field_label' => !empty($tag['labels'][0]) ? $tag['labels'][0] : $tag['name'],
				'_id' => $tag['name']
			);
		}
		
		return $fields;
	}

	private function convert_cf7_field_type($cf7_type) {
		$type_map = array(
			'email' => 'email',
			'url' => 'url',
			'tel' => 'tel',
			'textarea' => 'textarea',
			'checkbox' => 'checkbox',
			'radio' => 'checkbox',
			'file' => 'upload'
		);
		
		return isset($type_map[$cf7_type]) ? $type_map[$cf7_type] : 'text';
	}

	private function get_cf7_email_recipients($form) {
		$mail = $form->prop('mail');
		return $mail['recipient'] ?? '';
	}
	
	private function find_form_element($element_data,$post_id) {
	
		if(!$element_data['elType']){
			return;
		}
		
		if ( 'widget' === $element_data['elType'] && 'form' === $element_data['widgetType']
		|| 'widget' === $element_data['elType'] && 'ehp-form' === $element_data['widgetType'] ) {
	
			$this->forms[] = array(
				'post_id' => $post_id,
				'form_data' => $element_data,
			);
		}

		if ( ! empty( $element_data['elements'] ) ) {
			foreach ( $element_data['elements'] as $element ) {
				$this->find_form_element( $element,$post_id );
			}
		}
		
	}
	
	public function get_form_data($form_id,$post_id = false){
		
		if(!empty($this->get_froms())){
			foreach($this->get_froms() as $form){
				if($form['post_id'] == $post_id && $form_id == $form['form_data']['id']){
					return $form;
				}
				if(!$post_id && $form_id == $form['form_data']['id']){
					return $form;
				}
			}
		}
		
		return false;
		
	}
	
	public function display_forms_in_admin_panel(){
		$forms = $this->get_froms();
		if (empty($forms)) {
			echo "<h1>" . __('No forms found yet', 'elementor-leads') . "</h1>";
			echo "<p>" . __('To view forms, create at least one form', 'elementor-leads') . "</p>";
			return;
		}

		global $wpdb;

		echo "<table class='wp-list-table widefat fixed striped'>";
		
		$tabs = array(
			'form-name' => __('Form Name','elementor-leads'),
			'form-location' => __('Form Location','elementor-leads'),
			'form-type' => __('Form Type','elementor-leads'),
			'leads-count' => __('Leads Count','elementor-leads'),
			'email-recipes' => __('Email Recipients','elementor-leads'),
			'actions' => __('Actions','elementor-leads'),
		);
		echo "<tr>";
		foreach($tabs as $key => $label){
			$style = $key == 'actions' ? ' style="width:250px"' : false;
			$style = $key == 'leads-count' ? ' style="width:80px"' : $style;
			echo "<th$style>$label</th>";
		}
		echo "</tr>";

		foreach($forms as $form){
			$is_cf7 = isset($form['form_type']) && $form['form_type'] === 'cf7';
			$is_wpforms = isset($form['form_type']) && $form['form_type'] === 'wpforms';
			
			if (!isset($form['form_data']['settings']['form_name'])) {
				continue;
			}

			$form_name = $form['form_data']['settings']['form_name'];
			$post_id = isset($form['post_id']) ? $form['post_id'] : 0;
			$element_id = isset($form['form_data']['id']) ? $form['form_data']['id'] : 0;
			$is_global = get_post_meta($post_id, '_elementor_template_widget_type', true) === 'form';

			$page_name = $is_cf7 ? __('Contact Form 7', 'elementor-leads') : get_the_title($post_id);
			
			// fix elementor 2.1
			$form_slugs = array($element_id);
			$post_id_value = is_array($post_id) ? $post_id[0] : $post_id;
			$post_ids = array($post_id_value);

			if($included_posts = get_post_meta($post_id,'_elementor_global_widget_included_posts',true)){
				$post_ids = array_keys($included_posts);
				foreach($post_ids as $included_post_id ){
					$elementor_data = get_post_meta($included_post_id,'_elementor_data',true);
					$elementor_data_json = json_decode($elementor_data,true);

					$slug = recursive_get_forms_slugs($elementor_data_json,$post_id);
					if($slug){
						$form_slugs = array_merge($form_slugs,$slug);
					}
				}
				$post_ids[] = intval($post_id);
			}			
		
			$args = array(
				'post_type' => 'elementor_lead',
				'posts_per_page' => '-1',
				'meta_query' => array(
					'relation' => 'AND'
				)
			);
			
			if ($is_wpforms) {
				$args['meta_query'][] = array(
					'key' => 'form_slug',
					'value' => 'wpf_' . $post_id,
					'compare' => '='
				);
				$args['meta_query'][] = array(
					'key' => 'form_type',
					'value' => 'wpforms',
					'compare' => '='
				);
			} elseif ($is_cf7) {
				$args['meta_query'][] = array(
					'key' => 'form_slug',
					'value' => 'cf7_' . $post_id,
					'compare' => '='
				);
				$args['meta_query'][] = array(
					'key' => 'form_type',
					'value' => 'cf7',
					'compare' => '='
				);
			} else {
				// Elementor forms
				$is_global = get_post_meta($post_id, '_elementor_template_widget_type', true) === 'form';
				
				$args['meta_query'][] = array(
					'key' => 'form_slug',
					'value' => $element_id,
					'compare' => '='
				);

				if (!$is_global) {
					$args['meta_query'][] = array(
						'key' => 'post_id',
						'value' => $post_id,
						'compare' => '='
					);
				}
			}
			
			global $leads_query;;
			$leads_query = true;
			
			$query = new WP_Query( $args );

			$count_leads = $query->found_posts;
			wp_reset_postdata();
			
			$leads_query = false;

            $email_recipes = isset($form['form_data']['settings']['email_to'])? explode(',',$form['form_data']['settings']['email_to']):"";
			$email_recipes = $email_recipes != "" ? implode('<br>',$email_recipes) : "";
			
			$is_global = get_post_meta($post_id,'_elementor_template_widget_type',true);
			$is_global = $is_global && 'form' === $is_global;

			if ($is_cf7) {
				$form_type = __('Contact Form 7', 'elementor-leads');
			} else {
				$form_type = $is_global ? __('Global','elementor-leads').' '.'('.count($post_ids).')' : __('Single','elementor-leads');
			}

			
			echo "<tr>";
				$link = admin_url()."edit.php?post_type=elementor_lead&elementor_form={$element_id}&elementor_form_post_id={$post_id}";
				echo "<td><a href='$link'><b>$form_name</b></a></td>";
				echo "<td>";
				if ($is_cf7) {
					echo "<a href='".admin_url('admin.php?page=wpcf7&post='.$post_id)."'>$page_name</a>";
				} else {
					echo "<a target='_blank' href='".get_permalink($post_id)."'>$page_name</a></td>";
				}
				echo "<td>".$form_type."</td>";
				echo "<td>";
				if ($is_wpforms) {
					$form_slug = 'wpf_' . $post_id;
					$count_leads = $wpdb->get_var($wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->posts} p
						 INNER JOIN {$wpdb->postmeta} pm1 ON (p.ID = pm1.post_id AND pm1.meta_key = 'form_slug' AND pm1.meta_value = %s)
						 INNER JOIN {$wpdb->postmeta} pm2 ON (p.ID = pm2.post_id AND pm2.meta_key = 'form_type' AND pm2.meta_value = 'wpforms')
						 WHERE p.post_type = 'elementor_lead'",
						$form_slug
					));
				} elseif ($is_cf7) {
					$form_slug = 'cf7_' . $post_id;
					$count_leads = $wpdb->get_var($wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->posts} p
						 INNER JOIN {$wpdb->postmeta} pm1 ON (p.ID = pm1.post_id AND pm1.meta_key = 'form_slug' AND pm1.meta_value = %s)
						 INNER JOIN {$wpdb->postmeta} pm2 ON (p.ID = pm2.post_id AND pm2.meta_key = 'form_type' AND pm2.meta_value = 'cf7')
						 WHERE p.post_type = 'elementor_lead'",
						$form_slug
					));
				} else {
					// Elementor forms
					if ($is_global) {
						$count_leads = $wpdb->get_var($wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->posts} p
							 INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = 'form_slug' AND pm.meta_value = %s)
							 WHERE p.post_type = 'elementor_lead'",
							$element_id
						));
					} else {
						$count_leads = $wpdb->get_var($wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->posts} p
							 INNER JOIN {$wpdb->postmeta} pm1 ON (p.ID = pm1.post_id AND pm1.meta_key = 'form_slug' AND pm1.meta_value = %s)
							 INNER JOIN {$wpdb->postmeta} pm2 ON (p.ID = pm2.post_id AND pm2.meta_key = 'post_id' AND pm2.meta_value = %s)
							 WHERE p.post_type = 'elementor_lead'",
							$element_id,
							$post_id
						));
					}
				}
				$count_leads = intval($count_leads);
				echo "$count_leads</td>";
				echo "<td>$email_recipes</td>";
				echo "<td>";
					if($count_leads):
						
						/*echo "<a class='export button' href='$link&lenix_elementor_leads_export' target='_blank'>".
						__( 'Export leads to csv', 'elementor-leads' )
						."</a>";*/
						
						echo "<form>";
							echo "<input type='hidden' name='post_type' value='elementor_lead'>";
							echo "<input type='hidden' name='lenix_elementor_leads_export' value='1'>";
							echo "<input type='hidden' name='elementor_form' value='{$element_id}'>";
							echo "<input type='hidden' name='elementor_form_post_id' value='{$post_id}'>";
							echo "<div class='define-dates' style='display: none;'>";
								echo "<table>";
									echo "<tr>";
										echo "<td><label>".__( 'From', 'elementor-leads' )."</label></td>";
										echo "<td><input type='date' name='from_date'></td>";
									echo "</tr>";
									echo "<tr>";
										echo "<td><label>".__( 'To', 'elementor-leads' )."</label></td>";
										echo "<td><input type='date' name='to_date'></td>";
									echo "</tr>";
								echo "</table>";
							echo "</div>";
							
							echo "<a class='button show-define-dates'>".
						__( 'Define Dates', 'elementor-leads' )
						."</a>";
							
							echo "<button type='submit' class='export button button-primary'>".
						__( 'Export leads to csv', 'elementor-leads' )
						."</button>";
							
						echo "</form>";
						
					endif;
				echo "</td>";
			echo "</tr>";
		}
		
		echo "</table>";
		
		echo "<script>";
			echo "
			jQuery(document).on('click','.show-define-dates',function(){
				jQuery(this).closest('form').find('.define-dates').slideToggle('fast');
			});";
		echo "</script>";
		
	}
	public function elementor_leads_meta_box_add() {
		
		add_meta_box( 'FormFields', __('Form Fields','elementor-leads'), array($this,'elementor_leads_fields_meta_box'), 'elementor_lead', 'normal', 'high' );

	}
	
	public function elementor_leads_fields_meta_box( $post ) {

		wp_nonce_field( 'elementor_leads_meta_box_nonce', 'elementor_leads_meta_box_nonce' );
		
		$form_slug = get_post_meta($post->ID,'form_slug',true);
		$form_post_id = get_post_meta($post->ID,'post_id',true);
		$json_lead_data = get_post_meta($post->ID,'lead_data',true);
		$lead_data = json_decode($json_lead_data,true);

		?>
		
		<table class="lenix-leads-fields">
		
			<?php if( !empty($lead_data) ): ?>
		
				<?php foreach($lead_data as $field => $data): ?>
					<tr>
						<td width="30%"><?php echo $data['title'] ? $data['title'] : $data['type']; ?></td>
						<td width="70%">
							<?php
							if( isset($data['value']) ){
								display_value_by_type($data);
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			
			<?php endif; ?>
			
		</table>
		
		<style>
		.lenix-leads-fields {
			width: 100%;
		}
		.lenix-leads-fields td {
			padding: 5px;
			border: 1px solid #ddd;
		}
		.lenix-leads-fields td > * {
			width: 100%;
		}
		</style>
		<?php
		
	}
	
	public function elementor_leads_columns_head($defaults) {
	
		if( !isset($_GET['post_type']) || $_GET['post_type'] != 'elementor_lead'){
			return $defaults;
		}
		
		if(isset($_GET['elementor_form']) && isset($_GET['elementor_form_post_id'])):
			
			$form_slug = $_GET['elementor_form'];
			$form = $this->get_form_data($_GET['elementor_form'],$_GET['elementor_form_post_id']);
			$limit = apply_filters('lenix_elementor_leads_limit_admin_fields_cols',100);
			
			foreach($form['form_data']['settings']['form_fields'] as $field):
				if(!$limit){
					continue;
				}
				$limit--;
				$field_label = get_field_label_by_type($field);
			
				$field_id = isset($field['custom_id']) && $field['custom_id'] ? $field['custom_id'] : $field['_id'];
				$defaults[$field_id] = $field_label;
			endforeach;
		
		else:
			$defaults['form_data'] = __('Form Data','elementor-leads');
		endif;
		
		return $defaults;
	}
	 
	public function elementor_leads_columns_content($column_name, $post_ID) {
		
		$screen = get_current_screen();
		if ( 'elementor_lead' != $screen->post_type ){
			return;
		}
		
		$form_slug = isset($_GET['elementor_form']) ? $_GET['elementor_form'] : 0;	

		$lead_fields = get_post_meta($post_ID,'lead_data',true);
		$lead_fields = $lead_fields ? json_decode($lead_fields,true) : false;
		$is_form_data_column = $column_name == 'form_data';
		
		if(!empty($lead_fields)){
			
			echo $is_form_data_column ? '<table class="wp-list-table widefat fixed striped">' : false;
			
			foreach($lead_fields as $field => $data):				

				if ($column_name == $field || $is_form_data_column ) {
					echo  $is_form_data_column ? '<tr><th>'.($data['title'] ? $data['title'] : __('No Label','elementor-leads')).'</th><td>' : false;
					display_value_by_type($data);
					echo $is_form_data_column ? '</td></tr>' : false;
				}
				
			endforeach;
			
			echo $is_form_data_column ? '</table>' : false;
			
		} else {
			
			$form = $this->get_form_data($_GET['elementor_form'],$_GET['elementor_form_post_id']);
			$form_settings = $form['form_data']['settings']['form_fields'];
			foreach($form_settings as $field){
				$field_id = isset($field['custom_id']) && $field['custom_id'] ? $field['custom_id'] : $field['_id'];
				if ($column_name == $field_id) {
					$data['type'] = $field['field_type'];
					$val = get_post_meta($post_ID,$column_name,true);
					$data['value'] = $val ? $val : '-';
					display_value_by_type($data);
				}
			}
			
		}
		
		
		
	}
	
	public function show_list_of_elementor_forms() {
		$screen = get_current_screen();

		if ( 'elementor_lead' != $screen->post_type ){
			return;
		}
		
     	 
      
      	?>
		<div class="notice" style="padding: 0;border:0;">
          <?php
			
			$this->display_forms_in_admin_panel();
			?>
		</div>
	<?php          
		echo "<style>.search-box{display:none;}</style>"; 
	}
	

	public function remove_date_drop(){

		$screen = get_current_screen();

		if ( 'elementor_lead' == $screen->post_type ){
			add_filter('months_dropdown_results', '__return_empty_array');
          
		}
	}
	
	public function export_elementor_leads_to_csv(){
		
        if(!is_admin() || !( current_user_can('editor') || current_user_can('administrator') ) || !isset($_GET['lenix_elementor_leads_export']) || !isset($_GET['elementor_form']) || !isset($_GET['elementor_form_post_id']) ){
                return;
        }
            
		$form_slug = sanitize_key($_GET['elementor_form']);
		$form_post_id = sanitize_key($_GET['elementor_form_post_id']);
		
		if($form_slug && $form_post_id){
			
			$form = $this->get_form_data($form_slug,$form_post_id);
			$form_name = $form['form_data']['settings']['form_name'];
			
			lenix_download_send_headers("export-$form_name-" . date("d-m-Y") . ".csv");
			
			$csv_data[0][] = __('Date','elementor-leads');
			$csv_data[0][] = __('Time','elementor-leads');
			
			
			foreach($form['form_data']['settings']['form_fields'] as $field):
				$csv_data[0][] = html_entity_decode($field['field_label'], ENT_QUOTES, "utf-8");
			endforeach;
			
			$form_slugs = array($form_slug);
			$post_id_value = is_array($form_post_id) ? $form_post_id[0] : $form_post_id;
			$post_ids = array($post_id_value);
			
			if($included_posts = get_post_meta($form_post_id,'_elementor_global_widget_included_posts',true)){
				$post_ids = array_keys($included_posts);
				foreach($post_ids as $included_post_id ){
					$elementor_data = get_post_meta($included_post_id,'_elementor_data',true);
					$elementor_data_json = json_decode($elementor_data,true);

					$slug = recursive_get_forms_slugs($elementor_data_json,$form_post_id);
					if($slug){
						$form_slugs = array_merge($form_slugs,$slug);
					}
				}
				$post_ids[] = $form_post_id;
			}
			
			$args = array(
				'post_type'              => 'elementor_lead',
				'posts_per_page'         => '-1',
			);
			
			if($form_slug){
				$args['elementor_form'] = $form_slug;
			}
			
			if($form_post_id){
				$args['elementor_form_post_id'] = $form_post_id;
			}
			
			$from_date = lenix_get_query_field('from_date');
			$to_date = lenix_get_query_field('to_date');
			
			if($from_date || $to_date){
				
				$dates = array();
				
				if($from_date){
					$ymd = explode('-',$from_date);
					$dates['after'] = array(
						'year' => $ymd[0],
						'month' => $ymd[1],
						'day' => $ymd[2],
					);
				}
				
				if($to_date){
					$ymd = explode('-',$to_date);
					$dates['before'] = array(
						'year' => $ymd[0],
						'month' => $ymd[1],
						'day' => $ymd[2],
					);
				}
				
				$dates['inclusive'] = true;
				$args['date_query'] = array($dates);
				
			}

			
			global $leads_query;
			$leads_query = true;
			$query = new WP_Query( $args );
			$leads_query = false;

			// The Loop
			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$values = array();
					
					$post_time = get_post_time('U', true);
					$values[] = date('d-m-Y',$post_time);
					$values[] = date('h:i A',$post_time);
					
					$json_lead_data = get_post_meta(get_the_ID(),'lead_data',true);
					$lead_data = json_decode($json_lead_data,true);
					
					foreach($form['form_data']['settings']['form_fields'] as $field):
						
						if(isset($field['type']) && $field['type'] == 'html'){
							continue;
						}
						
						$field_id = isset($field['custom_id']) && $field['custom_id'] ? $field['custom_id'] : $field['_id'];

                        if($lead_data){

                            if(isset($lead_data[$field_id])){

                                $values[] = $lead_data[$field_id]['value'];

                            }

                        }
					endforeach;
					
					$csv_data[] = $values;
				}
			} else {
				echo __( 'No leads found yet', 'elementor-leads' );
			}

			// Restore original Post Data
			wp_reset_postdata();
			
			if(!empty($csv_data)){
				echo lenix_array_to_csv($csv_data);
			}
			
			die();
		}
	}
	
	public function filter_form_leads($query) {
		// Only filter in admin and for our post type
		if (!is_admin() || !$query->get('post_type') || $query->get('post_type') != 'elementor_lead') {
			return;
		}

		// Don't filter on the main leads listing page
		if (!isset($_GET['elementor_form']) || !isset($_GET['elementor_form_post_id'])) {
			return;
		}

		$form_slug = sanitize_text_field($_GET['elementor_form']);
		$post_id = sanitize_text_field($_GET['elementor_form_post_id']);
		
		// Check if this is a WPForms form
		if (strpos($form_slug, 'wpf_') === 0) {
			$meta_query = array(
				'relation' => 'AND',
				array(
					'key' => 'form_slug',
					'value' => 'wpf_' . $post_id,
					'compare' => '='
				),
				array(
					'key' => 'form_type',
					'value' => 'wpforms',
					'compare' => '='
				)
			);
		}
		// Check if this is a CF7 form
		elseif (strpos($form_slug, 'cf7_') === 0) {
			$meta_query = array(
				'relation' => 'AND',
				array(
					'key' => 'form_slug',
					'value' => 'cf7_' . $post_id,
					'compare' => '='
				),
				array(
					'key' => 'form_type',
					'value' => 'cf7',
					'compare' => '='
				)
			);
		}
		// This is an Elementor form
		else {
			$is_global = get_post_meta($post_id, '_elementor_template_widget_type', true) === 'form';
			
			$meta_query = array(
				'relation' => 'AND',
				array(
					'key' => 'form_slug',
					'value' => $form_slug,
					'compare' => '='
				)
			);

			if (!$is_global && $post_id) {
				$meta_query[] = array(
					'key' => 'post_id',
					'value' => $post_id,
					'compare' => '='
				);
			}
		}

		$query->set('meta_query', $meta_query);
	}
	
	private function get_wpforms_forms() {
		if (!class_exists('WPForms')) {
			return array();
		}

		if (!is_null($this->wpforms_forms)) {
			return $this->wpforms_forms;
		}

		$this->wpforms_forms = array();
		$forms = wpforms()->form->get();

		foreach ($forms as $form) {
			$form_data = wpforms_decode($form->post_content);
			
			$form_fields = array();
			foreach ($form_data['fields'] as $field) {
				$form_fields[] = array(
					'field_type' => $this->convert_wpforms_field_type($field['type']),
					'field_label' => $field['label'],
					'_id' => $field['id']
				);
			}

			$this->wpforms_forms[] = array(
				'post_id' => $form->ID,
				'form_data' => array(
					'settings' => array(
						'form_name' => $form->post_title,
						'form_fields' => $form_fields,
						'email_to' => $form_data['settings']['notifications'][0]['email'] ?? ''
					),
					'id' => 'wpf_' . $form->ID,
					'widgetType' => 'wpforms-form'
				),
				'form_type' => 'wpforms'
			);
		}

		return $this->wpforms_forms;
	}

	private function convert_wpforms_field_type($wpforms_type) {
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
	
	public function __construct(){
	
		add_action('lenix_elementor_leads_admin_options_page_section',array($this,'display_forms_in_admin_panel'));
		add_action('add_meta_boxes', array($this,'elementor_leads_meta_box_add') );
		add_filter('manage_posts_columns', array($this,'elementor_leads_columns_head'));
		add_action('manage_posts_custom_column', array($this,'elementor_leads_columns_content'), 10, 2);
		add_filter('views_edit-elementor_lead','__return_empty_array');
		add_action('admin_notices',array($this,'show_list_of_elementor_forms'));
		add_action('admin_head', array($this,'remove_date_drop'));
		add_action('init',array($this,'export_elementor_leads_to_csv'));		
		add_action('pre_get_posts',array($this,'filter_form_leads'));		
		
	}
	
}
new Lenix_Register_Elementor_Forms();