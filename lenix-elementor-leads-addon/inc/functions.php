<?php
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
		fputcsv($output, $fields);
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
    header("Content-Disposition: attachment;filename={$filename}");
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
	
	return sanitize_text_field($_GET[$field]);
	
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
        'referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
        'timestamp' => date('Y-m-d H:i:s'),
        'full_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]",
        'ip' => lenix_get_real_ip(),
        'user_agent' => lenix_get_browser_name(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''),
        'landing_page' => '',
        'landing_page_title' => '',
        'first_visit_time' => '',
        'initial_referrer' => ''
    );
    
    // Get first visit data from cookie
    if (isset($_COOKIE['lenix_first_visit'])) {
        try {
            $first_visit_data = json_decode(stripslashes($_COOKIE['lenix_first_visit']), true);
            if (is_array($first_visit_data)) {
                $result['landing_page'] = $first_visit_data['landing_page'] ?? '';
                $result['landing_page_title'] = $first_visit_data['landing_page_title'] ?? '';
                $result['first_visit_time'] = isset($first_visit_data['first_visit_time']) ? 
                    date('Y-m-d H:i:s', $first_visit_data['first_visit_time']) : '';
                $result['initial_referrer'] = $first_visit_data['initial_referrer'] ?? '';
            }
        } catch (Exception $e) {
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
        if (isset($_POST[$full_name]) && !empty($_POST[$full_name])) {
            $result[$full_name] = sanitize_text_field($_POST[$full_name]);
            continue;
        }
        
        // 3. Finally check cookies
        if (isset($_COOKIE['lenix_utms'])) {
            try {
                $cookie_data = json_decode(stripslashes($_COOKIE['lenix_utms']), true);
                if (is_array($cookie_data) && isset($cookie_data[$short_name]) && !empty($cookie_data[$short_name])) {
                    $result[$full_name] = $cookie_data[$short_name];
                }
            } catch (Exception $e) {
                // error_log('Error reading lead source cookie: ' . $e->getMessage());
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
    $parsed_url = parse_url($url);
    if (!isset($parsed_url['query'])) return null;

    parse_str($parsed_url['query'], $query_params);
    return $query_params[$param] ?? null;
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
        'CF-Connecting-IP', // Cloudflare (most accurate for Cloudflare)
        'HTTP_CF_CONNECTING_IP', // Cloudflare (less popular)
        'HTTP_X_REAL_IP', // Nginx 
        'HTTP_X_FORWARDED_FOR',  // Proxy forwarding
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR' // Default
    ];

    foreach ($headers as $key) {
        if (!empty($_SERVER[$key])) {
            $ip_list = explode(',', $_SERVER[$key]);
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