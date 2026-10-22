<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Leads list tools: bulk status change, bulk export, status/date filters,
 * search inside lead content and automatic retention clean-up.
 */
class Lenix_Lead_Tools {

	const EXPORT_ACTION = 'lenix_export_selected';
	const STATUS_PREFIX = 'lenix_set_status_';
	const CRON_HOOK     = 'lenix_leads_retention_cron';

	public function __construct() {
		add_filter( 'bulk_actions-edit-elementor_lead', array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-elementor_lead', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulk_notice' ) );

		add_action( 'restrict_manage_posts', array( $this, 'render_filters' ), 5 );
		add_action( 'pre_get_posts', array( $this, 'apply_filters' ) );
		add_filter( 'posts_search', array( $this, 'search_lead_content' ), 10, 2 );

		add_action( 'init', array( $this, 'schedule_retention' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_retention' ) );
	}

	private function can_edit() {
		return current_user_can( get_option( 'elementor_leads_edit_role', 'edit_others_posts' ) );
	}

	private function is_leads_list_query( $query ) {
		return is_admin() && $query->is_main_query() && 'elementor_lead' === $query->get( 'post_type' );
	}

	/* ---------------------------------------------------------------
	 * Bulk actions
	 * ------------------------------------------------------------- */

	public function register_bulk_actions( $actions ) {
		if ( ! $this->can_edit() ) {
			return $actions;
		}

		$terms = get_terms( array( 'taxonomy' => 'lead_status', 'hide_empty' => false ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				/* translators: %s: status name */
				$actions[ self::STATUS_PREFIX . $term->term_id ] = sprintf( __( 'Mark as %s', 'elementor-leads' ), $term->name );
			}
		}
		$actions[ self::EXPORT_ACTION ] = __( 'Export selected to CSV', 'elementor-leads' );

		return $actions;
	}

	public function handle_bulk_actions( $redirect_to, $action, $post_ids ) {
		if ( ! $this->can_edit() ) {
			return $redirect_to;
		}

		// Change status
		if ( 0 === strpos( $action, self::STATUS_PREFIX ) ) {
			$term_id = absint( substr( $action, strlen( self::STATUS_PREFIX ) ) );
			if ( ! $term_id || ! term_exists( $term_id, 'lead_status' ) ) {
				return $redirect_to;
			}

			$updated = 0;
			foreach ( $post_ids as $post_id ) {
				$post_id = absint( $post_id );
				if ( 'elementor_lead' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
					continue;
				}
				if ( ! is_wp_error( wp_set_object_terms( $post_id, array( $term_id ), 'lead_status' ) ) ) {
					$updated++;
				}
			}
			return add_query_arg( 'lenix_bulk_updated', $updated, $redirect_to );
		}

		// Export selected leads
		if ( self::EXPORT_ACTION === $action ) {
			$this->export_leads( array_map( 'absint', $post_ids ) );
		}

		return $redirect_to;
	}

	public function bulk_notice() {
		if ( ! isset( $_GET['lenix_bulk_updated'] ) || ! $this->can_edit() ) {
			return;
		}
		$count = absint( $_GET['lenix_bulk_updated'] );
		echo '<div class="notice notice-success is-dismissible"><p>' .
			esc_html( sprintf( _n( '%d lead updated.', '%d leads updated.', $count, 'elementor-leads' ), $count ) ) .
			'</p></div>';
	}

	/**
	 * Output the given leads as a CSV download and stop.
	 */
	private function export_leads( $lead_ids ) {
		$leads   = array();
		$columns = array();

		foreach ( $lead_ids as $lead_id ) {
			if ( 'elementor_lead' !== get_post_type( $lead_id ) || ! current_user_can( 'edit_post', $lead_id ) ) {
				continue;
			}
			$data = json_decode( (string) get_post_meta( $lead_id, 'lead_data', true ), true );
			$row  = array();
			if ( is_array( $data ) ) {
				foreach ( $data as $key => $field ) {
					if ( ! is_array( $field ) || ! isset( $field['value'] ) ) {
						continue;
					}
					$title = ! empty( $field['title'] ) ? $field['title'] : $key;
					// Same title on different forms ends up in the same column
					$columns[ $title ] = true;
					$row[ $title ]     = $field['value'];
				}
			}
			$statuses = wp_get_post_terms( $lead_id, 'lead_status', array( 'fields' => 'names' ) );
			$leads[]  = array(
				'id'     => $lead_id,
				'fields' => $row,
				'status' => is_wp_error( $statuses ) ? '' : implode( ', ', $statuses ),
			);
		}

		if ( empty( $leads ) ) {
			return;
		}

		$columns = array_keys( $columns );
		$csv     = array( array_merge( array( __( 'Date', 'elementor-leads' ), __( 'Time', 'elementor-leads' ), __( 'Status', 'elementor-leads' ) ), $columns ) );

		foreach ( $leads as $lead ) {
			$time = get_post_time( 'U', true, $lead['id'] );
			$row  = array( wp_date( 'd-m-Y', $time ), wp_date( 'H:i', $time ), $lead['status'] );
			foreach ( $columns as $title ) {
				$row[] = isset( $lead['fields'][ $title ] ) ? $lead['fields'][ $title ] : '';
			}
			$csv[] = $row;
		}

		lenix_download_send_headers( 'export-selected-leads-' . gmdate( 'd-m-Y' ) . '.csv' );
		echo lenix_array_to_csv( $csv ); // phpcs:ignore WordPress.Security.EscapeOutput -- CSV download
		exit;
	}

	/* ---------------------------------------------------------------
	 * Filters (status + dates) and search
	 * ------------------------------------------------------------- */

	public function render_filters( $post_type ) {
		if ( 'elementor_lead' !== $post_type ) {
			return;
		}

		$current_status = isset( $_GET['lead_status'] ) ? sanitize_title( wp_unslash( $_GET['lead_status'] ) ) : '';
		wp_dropdown_categories( array(
			'taxonomy'        => 'lead_status',
			'name'            => 'lead_status',
			'value_field'     => 'slug',
			'selected'        => $current_status,
			'show_option_all' => __( 'All statuses', 'elementor-leads' ),
			'hide_empty'      => false,
			'hierarchical'    => true,
		) );

		$from = $this->get_date_param( 'lenix_from' );
		$to   = $this->get_date_param( 'lenix_to' );
		echo '<label class="screen-reader-text" for="lenix_from">' . esc_html__( 'From', 'elementor-leads' ) . '</label>';
		echo '<input type="date" id="lenix_from" name="lenix_from" value="' . esc_attr( $from ) . '" title="' . esc_attr__( 'From', 'elementor-leads' ) . '"> ';
		echo '<label class="screen-reader-text" for="lenix_to">' . esc_html__( 'To', 'elementor-leads' ) . '</label>';
		echo '<input type="date" id="lenix_to" name="lenix_to" value="' . esc_attr( $to ) . '" title="' . esc_attr__( 'To', 'elementor-leads' ) . '"> ';
	}

	private function get_date_param( $name ) {
		if ( empty( $_GET[ $name ] ) ) {
			return '';
		}
		$value = sanitize_text_field( wp_unslash( $_GET[ $name ] ) );
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return $value;
		}
		return '';
	}

	public function apply_filters( $query ) {
		if ( ! $this->is_leads_list_query( $query ) ) {
			return;
		}

		$from = $this->get_date_param( 'lenix_from' );
		$to   = $this->get_date_param( 'lenix_to' );
		if ( $from || $to ) {
			$range = array( 'inclusive' => true );
			if ( $from ) {
				$range['after'] = $from;
			}
			if ( $to ) {
				$range['before'] = $to;
			}
			$query->set( 'date_query', array( $range ) );
		}
		// The lead_status filter works natively through the taxonomy query var.
	}

	/**
	 * Make the admin search look inside the stored form values as well as the title.
	 */
	public function search_lead_content( $search, $query ) {
		global $wpdb;

		if ( ! $search || ! $this->is_leads_list_query( $query ) ) {
			return $search;
		}

		$term = trim( (string) $query->get( 's' ) );
		if ( '' === $term ) {
			return $search;
		}

		// lead_data is stored as JSON where non-ASCII characters are \uXXXX escaped, so look for both forms.
		$variants = array( $term );
		$escaped  = trim( (string) wp_json_encode( $term ), '"' );
		if ( $escaped !== $term ) {
			$variants[] = $escaped;
		}

		$conditions = array( $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s", '%' . $wpdb->esc_like( $term ) . '%' ) );
		foreach ( $variants as $variant ) {
			$conditions[] = $wpdb->prepare(
				"EXISTS (SELECT 1 FROM {$wpdb->postmeta} lenix_pm WHERE lenix_pm.post_id = {$wpdb->posts}.ID AND lenix_pm.meta_key = 'lead_data' AND lenix_pm.meta_value LIKE %s)",
				'%' . $wpdb->esc_like( $variant ) . '%'
			);
		}

		return ' AND (' . implode( ' OR ', $conditions ) . ') ';
	}

	/* ---------------------------------------------------------------
	 * Retention
	 * ------------------------------------------------------------- */

	public function schedule_retention() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function unschedule_retention() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Permanently delete leads older than the configured number of days (0 = disabled).
	 */
	public function run_retention() {
		$days = absint( get_option( 'elementor_leads_retention_days', 0 ) );
		if ( ! $days ) {
			return;
		}

		global $wpdb;
		$values_table = $wpdb->prefix . 'lenix_custom_lead_values';

		$ids = get_posts( array(
			'post_type'      => 'elementor_lead',
			'post_status'    => 'any',
			'posts_per_page' => 200, // keep every run light - the next run continues
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'date_query'     => array( array( 'before' => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ), 'column' => 'post_date_gmt' ) ),
		) );

		foreach ( $ids as $id ) {
			$wpdb->delete( $values_table, array( 'lead_id' => $id ), array( '%d' ) );
			wp_delete_post( $id, true );
		}
	}
}

new Lenix_Lead_Tools();
