<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Neutralise spreadsheet formula injection (CSV injection) for a single cell.
 */
function lenix_csv_safe_cell( $value ) {
	if ( is_array( $value ) ) {
		$value = implode( ', ', array_map( 'strval', $value ) );
	}
	$value = (string) $value;
	// Plain phone numbers such as +972-50-1234567 are left untouched.
	if ( $value !== '' && strpos( "=+-@\t\r", $value[0] ) !== false && ! preg_match( '/^[+\-]?[0-9][0-9\s\-().]*$/', $value ) ) {
		$value = "'" . $value;
	}
	return $value;
}

/**
 * Sanitize the fields array that is stored as the `lead_data` meta.
 * Keys, titles, types and values are all treated as untrusted input.
 */
function lenix_sanitize_lead_fields( $fields ) {
	$clean = array();
	if ( ! is_array( $fields ) ) {
		return $clean;
	}

	foreach ( $fields as $key => $data ) {
		$key = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $key );
		if ( $key === '' || ! is_array( $data ) ) {
			continue;
		}

		$type  = isset( $data['type'] ) ? sanitize_key( $data['type'] ) : 'text';
		$entry = array(
			'id'    => isset( $data['id'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $data['id'] ) : $key,
			'type'  => $type,
			'title' => isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : '',
		);

		foreach ( array( 'value', 'raw_value' ) as $name ) {
			if ( ! isset( $data[ $name ] ) ) {
				continue;
			}
			$value = $data[ $name ];
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'sanitize_text_field', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ) );
			} elseif ( $type === 'textarea' ) {
				$value = sanitize_textarea_field( (string) $value );
			} else {
				$value = sanitize_text_field( (string) $value );
			}
			$entry[ $name ] = $value;
		}

		$clean[ $key ] = $entry;
	}

	return $clean;
}

function lenix_array_to_csv($array){
   if (count($array) == 0) {
     return null;
   }
	// create a file pointer connected to the output stream
	$output = fopen('php://output', 'w');

	// UTF-8 BOM
	fwrite($output, "\xEF\xBB\xBF");

	// output the column headings
	foreach($array as $fields){
		fputcsv($output, array_map('lenix_csv_safe_cell', $fields), ',', '"', '\\');
	}
	
	return ob_get_clean();
}

function lenix_download_send_headers($filename) {
    // disable caching
    $now = gmdate("D, d M Y H:i:s");
    header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
    header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
    header("Last-Modified: {$now} GMT");

    // force download  
    header("Content-Type: application/force-download");
    header("Content-Type: application/octet-stream");
    header("Content-Type: application/download");

    // disposition / encoding on response body
    $filename = sanitize_file_name($filename);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header("Content-Transfer-Encoding: binary");
	
}

//hide add new leads in admin bar
function lenix_elementor_leads_css_to_footer(){
	?>
<style>
	#wp-admin-bar-new-elementor_lead {
		display: none;
	}
	body.post-type-elementor_lead .wrap a.page-title-action {
		display: none;
	}
</style>
<?php }
add_action( 'admin_footer', 'lenix_elementor_leads_css_to_footer' );
add_action( 'wp_footer', 'lenix_elementor_leads_css_to_footer' );


function recursive_get_forms_slugs($arr,$id){
	
	global $slugs;
	
	if(!is_array($arr)){
		return $slugs;
	}
	
	foreach($arr as $data){
		if(isset($data['elements']) && !empty($data['elements'])){
			return recursive_get_forms_slugs($data['elements'],$id);
		}

		if(isset($data['templateID']) && $data['templateID'] == $id){
			$slugs[$data['id']] = $data['id'];
			return $slugs;
		}
	}
}

function lenix_get_query_field($field){
	
	if(!isset($_GET[$field])){
		return false;
	}
	
	return sanitize_text_field(wp_unslash($_GET[$field]));
	
}
/**
 * פונקציה כללית לקבלת מקור הליד
 */
function lenix_get_lead_source_data($post_id = null) {
    $utm_fields = array(
        'utm_source' => 'lenix_utm_source',
        'utm_medium' => 'lenix_utm_medium',
        'utm_campaign' => 'lenix_utm_campaign',
        'utm_term' => 'lenix_utm_term',
        'utm_content' => 'lenix_utm_content'
    );
    
    // Initialize result array with basic data
    $result = array(
        'referrer' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '',
        'timestamp' => current_time('mysql'),
        'full_url' => esc_url_raw((is_ssl() ? 'https://' : 'http://') . (isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '') . (isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '')),
        'ip' => lenix_get_real_ip(),
        'user_agent' => sanitize_text_field(lenix_get_browser_name(isset($_SERVER['HTTP_USER_AGENT']) ? wp_unslash($_SERVER['HTTP_USER_AGENT']) : '')),
        'landing_page' => '',
        'landing_page_title' => '',
        'first_visit_time' => '',
        'initial_referrer' => ''
    );
    
    // Get first visit data from cookie
    if (isset($_COOKIE['lenix_first_visit'])) {
        // Cookie contents are attacker controlled - sanitize every value.
        $first_visit_data = json_decode(wp_unslash($_COOKIE['lenix_first_visit']), true);
        if (is_array($first_visit_data)) {
            $result['landing_page'] = isset($first_visit_data['landing_page']) && is_scalar($first_visit_data['landing_page']) ? esc_url_raw($first_visit_data['landing_page']) : '';
            $result['landing_page_title'] = isset($first_visit_data['landing_page_title']) && is_scalar($first_visit_data['landing_page_title']) ? sanitize_text_field($first_visit_data['landing_page_title']) : '';
            $result['first_visit_time'] = isset($first_visit_data['first_visit_time']) && is_numeric($first_visit_data['first_visit_time']) ?
                gmdate('Y-m-d H:i:s', (int) $first_visit_data['first_visit_time']) : '';
            if (isset($first_visit_data['initial_referrer']) && is_scalar($first_visit_data['initial_referrer'])) {
                $referrer = (string) $first_visit_data['initial_referrer'];
                $result['initial_referrer'] = $referrer === 'Direct' ? 'Direct' : esc_url_raw($referrer);
            }
        }
    }
    
    // Process UTM parameters
    foreach ($utm_fields as $full_name => $short_name) {
        $result[$full_name] = '';
        
        // 1. Check referrer URL for UTM parameters
        if ($result['referrer'] && ($utm_value = lenix_extract_utm_from_url($result['referrer'], $full_name))) {
            if (!empty($utm_value)) {
                $result[$full_name] = sanitize_text_field($utm_value);
                continue;
            }
        }
        
        // 2. Check POST data
        if (isset($_POST[$full_name]) && is_scalar($_POST[$full_name]) && !empty($_POST[$full_name])) {
            $result[$full_name] = sanitize_text_field(wp_unslash($_POST[$full_name]));
            continue;
        }
        
        // 3. Finally check cookies
        if (isset($_COOKIE['lenix_utms'])) {
            $cookie_data = json_decode(wp_unslash($_COOKIE['lenix_utms']), true);
            if (is_array($cookie_data) && isset($cookie_data[$short_name]) && is_scalar($cookie_data[$short_name]) && !empty($cookie_data[$short_name])) {
                $result[$full_name] = sanitize_text_field((string) $cookie_data[$short_name]);
            }
        }
    }
    
    // Save data if post_id provided
    if ($post_id) {
        update_post_meta($post_id, '_lenix_lead_source_data', $result);
    }
    
    return $result;
}

/**
 * פונקציה לשליפת ערכי UTM מ-URL
 */
function lenix_extract_utm_from_url($url, $param) {
    $parsed_url = wp_parse_url($url);
    if (!isset($parsed_url['query'])) return null;

    parse_str($parsed_url['query'], $query_params);
    return isset($query_params[$param]) && is_scalar($query_params[$param]) ? $query_params[$param] : null;
}


function lenix_get_browser_name($user_agent) {
    // If there is no user agent, return a suspicious message
    if (empty($user_agent)) {
        return '[Suspicious] Empty UA';
    }

    // Clean the user agent and convert to lowercase
    $t = strtolower(trim($user_agent));
    
    // Add a space at the beginning to prevent false positives with strpos
    $t = " " . $t;

    // Array of trusted browsers
    $trusted_browsers = [
        'instagram' => '[Trusted] Instagram App',
        'fb_iab'    => '[Trusted] Facebook App',
        'fbav'      => '[Trusted] Facebook App',
        'whatsapp'  => '[Trusted] WhatsApp',
        'telegram'  => '[Trusted] Telegram',
        'line/'     => '[Trusted] LINE'
    ];

    // Array of suspicious browsers
    $suspicious_browsers = [
        'headless'  => '[Suspicious] Headless',
        'phantomjs' => '[Suspicious] PhantomJS',
        'selenium'  => '[Suspicious] Selenium',
        'puppet'    => '[Suspicious] Puppeteer'
    ];

    // Array of regular browsers
    $regular_browsers = [
        'chrome'    => 'Chrome',
        'firefox'   => 'Firefox',
        'safari'    => 'Safari',
        'edge'      => 'Edge',
        'opera'     => 'Opera',
        'opr/'      => 'Opera'
    ];

    // Array of known bots
    $known_bots = [
        'google'    => '[Bot] Googlebot',
        'bing'      => '[Bot] Bingbot',
        'yandex'    => '[Bot] Yandex'
    ];

    // Array of suspicious tools
    $suspicious_tools = [
        'curl'      => '[Suspicious] Curl',
        'wget'      => '[Suspicious] Wget',
        'python'    => '[Suspicious] Python',
        'ruby'      => '[Suspicious] Ruby',
        'perl'      => '[Suspicious] Perl'
    ];

    // Check for short User Agent
    if (strlen($t) < 30) {
        return '[Suspicious] Short UA';
    }

    // Check for trusted browsers
    foreach ($trusted_browsers as $key => $value) {
        if (strpos($t, $key) !== false) {
            return $value;
        }
    }

    // Check for suspicious browsers
    foreach ($suspicious_browsers as $key => $value) {
        if (strpos($t, $key) !== false) {
            return $value;
        }
    }

    // Check for regular browsers
    foreach ($regular_browsers as $key => $value) {
        if (strpos($t, $key) !== false) {
            return $value;
        }
    }

    // Check for known bots
    foreach ($known_bots as $key => $value) {
        if (strpos($t, $key) !== false) {
            return $value;
        }
    }

    // Check for suspicious tools
    foreach ($suspicious_tools as $key => $value) {
        if (strpos($t, $key) !== false) {
            return $value;
        }
    }

    // Check for generic bot patterns
    $bot_patterns = ['bot', 'crawler', 'spider', 'http'];
    foreach ($bot_patterns as $pattern) {
        if (strpos($t, $pattern) !== false) {
            return '[Bot] Generic';
        }
    }

    // If no match found, return the first 50 characters of the UA
    return '[Unknown] ' . substr($user_agent, 0, 50);
}

function lenix_get_real_ip() {
    $headers = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_REAL_IP', // Nginx 
        'HTTP_X_FORWARDED_FOR',  // Proxy forwarding
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR' // Default
    ];

    /**
     * Filter the $_SERVER keys used to detect the visitor IP, in priority order.
     * Return array('REMOTE_ADDR') if the site is not behind a proxy, as the other
     * headers can be spoofed by the visitor.
     */
    $headers = apply_filters('lenix_leads_ip_headers', $headers);

    foreach ($headers as $key) {
        if (!empty($_SERVER[$key])) {
            $ip_list = explode(',', sanitize_text_field(wp_unslash($_SERVER[$key])));
            foreach ($ip_list as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }

    return '127.0.0.1';
}

/**
 * Save lead data
 */
function lenix_save_lead($post_id, $lead_data) {
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
    
    // Save lead data as post meta
    update_post_meta($post_id, 'lead_data', wp_json_encode($lead_data));
    
    // Allow other plugins to hook into lead save
    do_action('lenix_after_lead_save', $post_id);
    
    return $post_id;
}