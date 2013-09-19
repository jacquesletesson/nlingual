<?php
/*
 * Detect and set the requested language
 */
add_action('init', 'nLingual_detect_requested_language');
function nLingual_detect_requested_language(){
	$post_var = nL_get_option('post_var');
	$get_var = nL_get_option('get_var');
	$method = nL_get_option('method');

	$alang = $_SERVER['HTTP_ACCEPT_LANGUAGE'];

	$lang = null;
	$lock = false;

	// First, use the HTTP_ACCEPT_LANGUAGE method if valid
	if(nL_lang_exists($alang)){
		$lang = $alang;
		$lock = false;
	}

	// Proceed based on method
	switch($method){
		case NL_REDIRECT_USING_DOMAIN:
			$host = $_SERVER['HTTP_HOST'];

			// Check if a language slug is present and is an existing language
			if(preg_match('#^([a-z]{2})\.#i', $host, $match) && nL_lang_exists($match[1])){
				$lang = $match[1];
				$_SERVER['HTTP_HOST'] = substr($host, 3); // Recreate the hostname sans the language slug at the beginning
			}
			break;
		case NL_REDIRECT_USING_PATH:
			$uri = $_SERVER['REQUEST_URI'];

			// Get the path of the home URL, with trailing slash
			$home = trailingslashit(parse_url(get_option('home'), PHP_URL_PATH));

			// Strip the home path from the beginning of the URI
			$uri = substr($uri, strlen($home)); // Now /en/... or /mysite/en/... will become en/...

			// Check if a language slug is present and is an existing language
			if(preg_match('#^([a-z]{2})(/.*)?$#i', $uri, $match) && nL_lang_exists($match[1])){
				$lang = $match[1];
				$_SERVER['REQUEST_URI'] = $home.substr($uri, 3); // Recreate the url sans the language slug and slash after it
			}
			break;
	}

	// Override with get_var method if present and valid
	if($get_var && isset($_GET[$get_var]) && nL_lang_exists($_GET[$get_var])){
		$lang = $_GET[$get_var];
	}

	// Override with post_var method if present and valid
	if($post_var && isset($_POST[$post_var]) && nL_lang_exists($_POST[$post_var])){
		$lang = $_POST[$post_var];
	}

	if($lang) nL_set_lang($lang, $lock);
}

/*
 * Check if a translated version of the front page is being requested,
 * adjust query to treat it as the front page
 */
add_action('parse_request', 'nLingual_check_alternate_frontpage');
function nLingual_check_alternate_frontpage(&$wp){
	global $wpdb;
	if(!is_admin() && isset($wp->query_vars['pagename'])){
		$name = basename($wp->query_vars['pagename']);
		$id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type != 'revision'", $name));

		if(!nL_in_default_lang($id)){
			$lang = nL_get_lang($id);
			$orig = nL_get_translation($id, true);

			if($orig == get_option('page_on_front')){
				$wp->query_vars = array();
				$wp->request = null;
				$wp->matched_rule = null;
				$wp->matched_query = null;
			}

			nL_set_lang($lang);
		}
	}
}

/*
 * Set the language query_var if on the front end and requesting a language supporting post type
 */
add_action('parse_query', 'nLingual_set_language_query_var');
function nLingual_set_language_query_var(&$wp_query){
	if(!is_admin() && in_array($wp_query->query_vars['post_type'], nL_post_types()) && !isset($wp_query->query_vars['language'])){
		$wp_query->query_vars['language'] = nL_get_lang();
	}
}

/*
 * Detect the language of the requested post and apply
 */
add_action('wp', 'nLingual_detect_requested_post_language');
function nLingual_detect_requested_post_language(&$wp){
	global $wp_query;
	if(!is_admin()){
		if(isset($wp_query->post)){
			$lang = nL_get_post_lang($wp_query->post->ID);
			nL_set_lang($lang);
		}

		// Now that the language is definitely set,
		// override the $wp_locale
		global $wp_locale;
		// Load the nLingual local class
		require(__DIR__.'/nLingual_WP_Locale.php');
		$wp_locale = new nLingual_WP_Locale();
	}
}

/*
 * Intercept and replace the .mo filename to look for
 */
add_filter('locale', 'nLingual_intercept_local_name');
function nLingual_intercept_local_name($locale){
	if(!is_admin() && $mo = nL_get_lang('mo')){
		return $mo;
	}
	return $locale;
}

/*
 * Replace the page_on_front and page_for_posts values with the translated version
 */
add_filter('option_page_on_front', 'nLingual_get_curlang_version');
add_filter('option_page_for_posts', 'nLingual_get_curlang_version');
function nLingual_get_curlang_version($value){
	if(!is_admin()){
		$value = nL_get_translation($value);
	}
	return $value;
}


/*
 * Add fitler for running split_langs on the blogname and the_title
 */
add_filter('option_blogname', 'nL_split_langs');
add_filter('the_title', 'nL_split_langs');

/*
 * If l10n_dateformat option is true, add fitler for localizing the date_format vlaue
 */
if(nL_get_option('l10n_dateformat')){
	add_filter('option_date_format', 'nLingual_l10n_date_format');
	function nLingual_l10n_date_format($format){
		if(!is_admin()){
			$format = __($format, wp_get_theme()->get('TextDomain'));
		}

		return $format;
	}
}

/*
 * Fix class names that contain %'s (because their encoded non-ascii names, and add the lang-[lang] class
 */
add_filter('body_class', 'nLingual_add_language_body_class');
function nLingual_add_language_body_class($classes){
	global $wpdb;
	$object = get_queried_object();
	foreach($classes as &$class){
		if(strpos($class, '%') !== false){
			$lang = nL_get_post_lang($object->ID);
			$orig = nL_get_original_post($object->ID);
			$class = $wpdb->get_var($wpdb->prepare("SELECT post_name FROM $wpdb->posts WHERE ID = %d", $orig));
			$class .= " $class--$lang";
		}
	}

	$classes[] = "lang-".nL_get_lang();

	return $classes;
}

/*
 * Update lang attribute to use the current languages ISO name
 */
add_filter('language_attributes', 'nLingual_html_language_attributes');
function nLingual_html_language_attributes($atts){
	$atts = preg_replace('/lang=".+?"/', 'lang="'.nL_get_lang('iso').'"', $atts);
	return $atts;
}