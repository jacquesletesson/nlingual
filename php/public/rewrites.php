<?php
// ========================= //
//	General Rewrite Filters  //
// ========================= //

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
 * Add fitler for running split_langs on the blogname
 */
add_filter('option_blogname', 'nL_split_langs');

/*
 * Add filter for localizing the permalinks
 */
add_filter('author_feed_link',				'nL_localize_url');
add_filter('author_feed_link',				'nL_localize_url');
add_filter('author_link',					'nL_localize_url');
add_filter('category_feed_link',			'nL_localize_url');
add_filter('category_link',					'nL_localize_url');
add_filter('day_link',						'nL_localize_url');
add_filter('feed_link',						'nL_localize_url');
add_filter('get_comment_author_url_link',	'nL_localize_url');
add_filter('get_pagenum_link',				'nL_localize_url');
add_filter('home_url',						'nL_localize_url');
add_filter('month_link',					'nL_localize_url');
add_filter('post_comments_feed_link',		'nL_localize_url');
add_filter('site_url',						'nL_localize_url');
add_filter('tag_feed_link',					'nL_localize_url');
add_filter('tag_link',						'nL_localize_url');
add_filter('term_link',						'nL_localize_url');
add_filter('the_permalink',					'nL_localize_url');
add_filter('year_link',						'nL_localize_url');

add_filter('page_link', 'nLingual_localize_page_permalink', 10, 2);
function nLingual_localize_page_permalink($link, $post_id){
	$link = nL_localize_url($link, nL_get_post_lang($post_id, true), false, true);
	return $link;
}

add_filter('post_link', 'nLingual_localize_post_permalink', 10, 2);
function nLingual_localize_post_permalink($link, $post){
	$link = nL_localize_url($link, nL_get_post_lang($post->ID, true), false, true);
	return $link;
}

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
		$class = str_replace('%', '-', $class);
	}

	$classes[] = "lang-".nL_current_lang();

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