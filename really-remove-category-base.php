<?php
/**
 * Plugin Name: Really Remove Category Base
 * Plugin URI: https://wordpress.org/plugins/really-remove-category-base/
 * Description: Really Remove Category Base is an easy WordPress Plugin. To remove the category base from permalink, use this plugin.
 * Version: 1.0
 * Text Domain: really-remove-category-base
 * Author: Tema Pazarı
 * Author URI: https://temapazari.com
 *
 * @package ReallyRemoveCategoryBase
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'RRCB_PLUGIN_DIR' ) ) {
	define( 'RRCB_PLUGIN_DIR', __FILE__ );
}

if ( ! defined( 'RRCB_PLUGIN_URL' ) ) {
	define( 'RRCB_PLUGIN_URL', untrailingslashit( plugins_url( '/', RRCB_PLUGIN_DIR ) ) );
}

if ( ! defined( 'RRCB_VERSION' ) ) {
	define( 'RRCB_VERSION', '1.0.0' );
}

register_activation_hook( __FILE__, 'really_remove_category_refresh_rules' );

add_action( 'created_category', 'really_remove_category_refresh_rules' );
add_action( 'edited_category', 'really_remove_category_refresh_rules' );
add_action( 'delete_category', 'really_remove_category_refresh_rules' );

/**
 * Register activation hook for this plugin by invoking activate.
 *
 * @since 1.0
 */
function really_remove_category_refresh_rules() {
	add_option( 'really_remove_category_base_rewrite_rules_flush', true );
}


/**
 * Register deactivation hook for this plugin by invoking deactivate.
 *
 * @since 1.0
 */
function really_remove_category_base_deactivate() {
	remove_filter( 'category_rewrite_rules', 'really_remove_category_refresh_rules' ); // We don't want to insert our custom rules again.
	delete_option( 'really_remove_category_base_rewrite_rules_flush' );
}
register_deactivation_hook( __FILE__, 'really_remove_category_base_deactivate' );


/**
 * Remove category base
 *
 * @since 1.0
 */
function really_remove_category_base_perma_struct() {
	global $wp_rewrite;
	$wp_rewrite->extra_permastructs['category'][0] = '%category%';

	$b_found = false;
	$a_rules = get_option( 'rewrite_rules' );
	if ( $a_rules && count( $a_rules ) > 0 ) {
		foreach ( $a_rules as $key => $value ) {
			if ( 'rrcb-Jm12yZUK7lXdR92M3rWc' === $key ) {
				$b_found = true;
				break;
			}
		}
	}

	if ( ! $b_found || get_option( 'really_remove_category_base_rewrite_rules_flush' ) ) {
		flush_rewrite_rules();
		delete_option( 'really_remove_category_base_rewrite_rules_flush' );
	}
}
add_action( 'init', 'really_remove_category_base_perma_struct', 999999 );


/**
 * Add our custom category rewrite rules.
 *
 * @param array $cat_rewrite Array of rewrite rules generated for the current permastruct, keyed by their regex pattern.
 *
 * @since 1.0
 */
function really_remove_category_base_rewrite_rules( $cat_rewrite ) {
	global $wp_rewrite;

	$b_amp = false;
	foreach ( $cat_rewrite as $k => $v ) {
		if ( stripos( $k, '/amp' ) !== false ) {
			$b_amp = true;
		}
	}

	// First we need to get full URLs of our pages.
	$pages      = get_pages( 'number=0' );
	$pages_urls = array();
	foreach ( $pages as $pages_item ) {
		$pages_urls[] = trim( str_replace( get_bloginfo( 'url' ), '', get_permalink( $pages_item->ID ) ), '/' );
	}

	$cat_rewrite = array();
	$categories       = get_categories( array( 'hide_empty' => false ) );
	foreach ( $categories as $cat ) {
		$cat_nicename = $cat->slug;
		if ( $cat->parent === $cat->cat_ID ) { // recursive recursion.
			$cat->parent = 0;
		} elseif ( 0 !== $cat->parent ) {
			$cat_nicename = get_category_parents( $cat->parent, false, '/', true ) . $cat_nicename;
		}

		// Let's check if any of the category full URLs matches any of the pages.
		if ( in_array( $cat_nicename, $pages_urls, true ) ) {
			continue;
		}

		$cat_rewrite[ '(' . $cat_nicename . ')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$' ]                = 'index.php?category_name=$matches[1]&feed=$matches[2]';
		$cat_rewrite[ '(' . $cat_nicename . ')/' . $wp_rewrite->pagination_base . '/?([0-9]{1,})/?$' ] = 'index.php?category_name=$matches[1]&paged=$matches[2]';
		if ( $b_amp ) {
			$cat_rewrite[ '(' . $cat_nicename . ')/amp/' . $wp_rewrite->pagination_base . '/?([0-9]{1,})/?$' ] = 'index.php?amp&category_name=$matches[1]&paged=$matches[2]';
			$cat_rewrite[ '(' . $cat_nicename . ')/amp/?$' ] = 'index.php?amp&category_name=$matches[1]';
		}
		$cat_rewrite[ '(' . $cat_nicename . ')/?$' ] = 'index.php?category_name=$matches[1]';
	}

	// Redirect support from Old Category Base.
	$cat_base 											= get_option( 'category_base' );
	$old_category_base									= trim( $cat_base ? $cat_base : 'category', '/' );
	$cat_rewrite[ $old_category_base . '/(.*)$' ]	= 'index.php?rrcb_category_redirect=$matches[1]';
	$cat_rewrite['rrcb-Jm12yZUK7lXdR92M3rWc'] = 'index.php?rrcb-Jm12yZUK7lXdR92M3rWc=1';

	return $cat_rewrite;
}
add_filter( 'category_rewrite_rules', 'really_remove_category_base_rewrite_rules' );

/**
 * Add 'rrcb_category_redirect' query variable.
 *
 * @param array $public_query_vars The array of allowed query variable names.
 *
 * @since 1.0
 */
function really_remove_category_base_query_vars( $public_query_vars ) {
	$public_query_vars[] = 'rrcb_category_redirect';
	return $public_query_vars;
}
add_filter( 'query_vars', 'really_remove_category_base_query_vars' );

/**
 * Redirect if 'rrcb_category_redirect' is set.
 *
 * @param array $query_vars Request data in WP_Http format.
 *
 * @since 1.0
 */
function really_remove_category_base_request( $query_vars ) {
	if ( isset( $query_vars['rrcb_category_redirect'] ) ) {
		$catlink = trailingslashit( get_option( 'home' ) ) . user_trailingslashit( $query_vars['rrcb_category_redirect'], 'category' );
		status_header( 301 );
		header( "Location: $catlink" );
		exit();
	}
	return $query_vars;
}
add_filter( 'request', 'really_remove_category_base_request' );


/**
 * Change category link.
 *
 * @param string $link Category link URL.
 *
 * @since 1.0
 */
function really_remove_category_base_cat_link( $link ) {
	$cat_base = get_option( 'category_base' );

	// WP uses "category/" as the default.
	if ( '' === $cat_base ) {
		$cat_base = 'category';
	}

	// Remove initial slash, if there is one (we remove the trailing slash in the regex replacement and don't want to end up short a slash).
	if ( substr( $cat_base, 0, 1 ) === '/' ) {
		$cat_base = substr( $cat_base, 1 );
	}

	$cat_base .= '/';

	return preg_replace( '|' . $cat_base . '|', '', $link, 1 );
}
add_filter( 'category_link', 'really_remove_category_base_cat_link' );
