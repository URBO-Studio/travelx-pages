<?php
/**
 * Plugin Name:       TravelX Home
 * Description:       Sends travelx.bg/ straight to the current event page (now /plovdiv/), and the pages of past events to it once their own plugin is switched off.
 * Version:           1.0.0
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Author:            URBO Studio
 *
 * How it works
 * ------------
 * The front page (WordPress page 742, the old city picker) is left as it is;
 * visitors to / are redirected before it renders. The redirect is a 302, so
 * browsers and search engines don't hold on to it when the next event replaces
 * this one: change TXH_CURRENT, bump TXH_VERSION, rebuild, upload.
 *
 * Past events in TXH_RETIRED are redirected only when WordPress itself is asked
 * for them, which happens only once their static folder is gone (that event's
 * plugin is deactivated). While the folder exists, the web server serves it
 * directly and this plugin never runs for it.
 *
 * Deactivating this plugin brings the old front page back as it was.
 */

defined( 'ABSPATH' ) || exit;

const TXH_VERSION = '1.0.0';
const TXH_CURRENT = 'plovdiv';
const TXH_RETIRED = array( 'sliven' );

function txh_target() {
	$query = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY );
	return home_url( '/' . TXH_CURRENT . '/' ) . ( '' !== $query ? '?' . $query : '' );
}

// Priority 1: after the event plugins' own fallbacks (priority 0), before
// WordPress's canonical redirects (priority 10).
add_action( 'template_redirect', function () {
	if ( is_preview() || is_customize_preview() ) {
		return;
	}

	if ( is_front_page() && ! is_paged() ) {
		wp_safe_redirect( txh_target(), 302, 'TravelX Home' );
		exit;
	}

	if ( is_404() ) {
		$path  = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		$first = strtok( $path, '/' );
		if ( false !== $first && in_array( $first, TXH_RETIRED, true ) ) {
			wp_safe_redirect( txh_target(), 302, 'TravelX Home' );
			exit;
		}
	}
}, 1 );
