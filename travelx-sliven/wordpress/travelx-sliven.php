<?php
/**
 * Plugin Name:       TravelX Sliven
 * Description:       Publishes the TravelX Urban Experiences page (Сливен, 21.11.2026) as a static page at /sliven/ and prerenders it when a visitor hovers a link to it.
 * Version:           1.0.8
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Author:            URBO Studio
 *
 * How it works
 * ------------
 * The page lives in site/ inside this plugin. On activation, and after every
 * update, it is copied to <site root>/sliven/, where LiteSpeed serves it as a
 * plain file — WordPress, the theme and the other plugins never run for it.
 * If the site root isn't writable, WordPress serves the same file from the
 * plugin folder instead (slower, but it works).
 *
 * Deactivating the plugin removes /sliven/. It only ever deletes a folder that
 * carries its own marker file, never one it didn't create.
 *
 * To update the page: edit it, bump TXS_VERSION, run `python tools/build.py`,
 * then upload the zip under Plugins → Add New → Upload Plugin and choose
 * "Replace current with uploaded".
 */

defined( 'ABSPATH' ) || exit;

const TXS_VERSION = '1.0.21';
const TXS_SLUG    = 'sliven';
const TXS_MARKER  = '.travelx-sliven';

function txs_source() {
	return __DIR__ . '/site';
}

function txs_target() {
	return untrailingslashit( ABSPATH ) . '/' . TXS_SLUG;
}

/** True if $dir holds a file at any depth. Empty sub-folders don't count. */
function txs_has_files( $dir ) {
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $files as $file ) {
		return true;
	}
	return false;
}

/**
 * Ours to replace: nothing there yet, a folder this plugin published, or an
 * empty folder someone else left behind (a hosting-panel subdomain root, say)
 * — there is nothing in it to lose.
 */
function txs_owns_target() {
	$target = txs_target();
	return ! file_exists( $target )
		|| is_file( $target . '/' . TXS_MARKER )
		|| ( is_dir( $target ) && ! txs_has_files( $target ) );
}

function txs_rmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $items as $item ) {
		$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
	}
	rmdir( $dir );
}

/**
 * Copy site/ to <root>/sliven/. The copy is built beside the live folder and
 * swapped in, so nobody ever loads a half-copied page.
 */
function txs_publish() {
	if ( ! txs_owns_target() ) {
		return false;
	}
	$target = txs_target();
	$tmp    = $target . '.tmp-' . uniqid();
	// Modes are set explicitly: wp_mkdir_p() copies the parent folder's mode,
	// and where the site root is 0750 (the web server gets in through the
	// group) the new folders end up unreadable to the web server.
	if ( ! wp_mkdir_p( $tmp ) || ! chmod( $tmp, 0755 ) ) {
		return false;
	}
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( txs_source(), FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ( $items as $item ) {
		$to = $tmp . '/' . $items->getSubPathname();
		$ok = $item->isDir()
			? wp_mkdir_p( $to ) && chmod( $to, 0755 )
			: copy( $item->getPathname(), $to ) && chmod( $to, 0644 );
		if ( ! $ok ) {
			txs_rmdir( $tmp );
			return false;
		}
	}
	file_put_contents( $tmp . '/' . TXS_MARKER, TXS_VERSION );
	if ( is_dir( $target ) && ! is_file( $target . '/' . TXS_MARKER ) ) {
		update_option( 'txs_adopted', 1, false ); // an empty folder was already here — put it back on deactivation
	}
	txs_rmdir( $target );
	return rename( $tmp, $target );
}

function txs_sync() {
	update_option( 'txs_state', txs_publish() ? TXS_VERSION : 'failed-' . TXS_VERSION, false );
}
register_activation_hook( __FILE__, 'txs_sync' );

// "Replace current with uploaded" doesn't re-run activation, so republish on
// the first admin page load after the version changes.
add_action( 'admin_init', function () {
	$state = get_option( 'txs_state' );
	if ( TXS_VERSION !== $state && 'failed-' . TXS_VERSION !== $state ) {
		txs_sync();
	}
} );

register_deactivation_hook( __FILE__, function () {
	if ( is_file( txs_target() . '/' . TXS_MARKER ) ) {
		txs_rmdir( txs_target() );
		if ( get_option( 'txs_adopted' ) ) {
			wp_mkdir_p( txs_target() ); // leave it as we found it
		}
	}
	delete_option( 'txs_state' );
	delete_option( 'txs_adopted' );
} );

add_action( 'admin_notices', function () {
	if ( 'failed-' . TXS_VERSION !== get_option( 'txs_state' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	if ( txs_owns_target() ) {
		$why = 'WordPress could not write the <code>/sliven/</code> folder to the site root, so the page is being served through WordPress from the plugin folder instead. It works, just a little slower.';
	} else {
		$found = array_slice( array_values( array_diff( scandir( txs_target() ), array( '.', '..' ) ) ), 0, 8 );
		$why   = 'A <code>/sliven/</code> folder this plugin did not create already exists in the site root and holds files, so it was left alone — and the server shows it instead of this page. It contains: <code>' . esc_html( implode( ', ', $found ) ) . '</code>. Move those files elsewhere, then deactivate and reactivate the plugin.';
	}
	echo '<div class="notice notice-warning"><p><strong>TravelX Sliven:</strong> ' . $why . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed strings
} );

// Fallback: only reached when the static folder isn't there — when it is,
// LiteSpeed answers /sliven/ before WordPress ever loads.
add_action( 'template_redirect', function () {
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( TXS_SLUG !== $path ) {
		return;
	}
	$html = file_get_contents( txs_source() . '/index.html' );
	$html = preg_replace( '#(?<=["\'\s(,])assets/#', plugins_url( 'site/assets/', __FILE__ ), $html );
	status_header( 200 );
	header( 'Content-Type: text/html; charset=utf-8' );
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- our own static page
	exit;
}, 0 );

// Prerender /sliven/ while a visitor hovers a link to it, so the click lands
// instantly. WordPress core already prefetches internal links on click; this
// adds a prerender for this one page only.
add_action( 'wp_load_speculation_rules', function ( $rules ) {
	$rules->add_rule( 'prerender', 'travelx-sliven', array(
		'source'    => 'document',
		'where'     => array( 'href_matches' => '/' . TXS_SLUG . '/*' ),
		'eagerness' => 'moderate',
	) );
} );

// Cross-fade into the page. Both documents must opt in; this is the WordPress
// side, scoped so every other navigation on the site stays exactly as it was.
add_action( 'wp_head', function () {
	?>
<style id="txs-view-transition">@media (prefers-reduced-motion:no-preference){@view-transition{navigation:auto}}</style>
<script id="txs-view-transition-scope">
addEventListener('pageswap',function(e){if(!e.viewTransition)return;var to=e.activation&&e.activation.entry&&e.activation.entry.url;if(!to||new URL(to).pathname.indexOf('/<?php echo esc_js( TXS_SLUG ); ?>/')!==0)e.viewTransition.skipTransition();});
</script>
	<?php
}, 1 );

// ── Diagnostics: Tools → TravelX Sliven ─────────────────────────────
// A read-only report of where the page was published and what the web
// server actually returns for it, plus a button to republish without
// re-uploading the plugin.

add_action( 'admin_menu', function () {
	add_management_page( 'TravelX Sliven', 'TravelX Sliven', 'manage_options', 'travelx-sliven', 'txs_diagnostics_page' );
} );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	$links[] = '<a href="' . esc_url( admin_url( 'tools.php?page=travelx-sliven' ) ) . '">Diagnostics</a>';
	return $links;
} );

function txs_listing( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return file_exists( $dir ) ? '(exists, but is not a folder)' : '(does not exist)';
	}
	$lines = array();
	foreach ( array_diff( scandir( $dir ), array( '.', '..' ) ) as $name ) {
		$path    = $dir . '/' . $name;
		$lines[] = sprintf( '%s  %s%s', substr( sprintf( '%o', fileperms( $path ) ), -4 ), $name, is_dir( $path ) ? '/' : '  (' . filesize( $path ) . ' B)' );
	}
	return $lines ? implode( "\n", $lines ) : '(empty folder)';
}

/** "0755  owner:group" for a path. */
function txs_mode( $path ) {
	if ( ! file_exists( $path ) ) {
		return '(missing)';
	}
	$stat  = stat( $path );
	$owner = $stat['uid'];
	$group = $stat['gid'];
	if ( function_exists( 'posix_getpwuid' ) && ( $pw = posix_getpwuid( $owner ) ) ) {
		$owner = $pw['name'];
	}
	if ( function_exists( 'posix_getgrgid' ) && ( $gr = posix_getgrgid( $group ) ) ) {
		$group = $gr['name'];
	}
	return substr( sprintf( '%o', $stat['mode'] ), -4 ) . '  ' . $owner . ':' . $group;
}

function txs_diagnostics_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['txs_republish'] ) && check_admin_referer( 'txs_republish' ) ) {
		txs_sync();
		echo '<div class="notice notice-info"><p>Republished. State is now <code>' . esc_html( (string) get_option( 'txs_state' ) ) . '</code>.</p></div>';
	}

	$target  = txs_target();
	$docroot = untrailingslashit( isset( $_SERVER['DOCUMENT_ROOT'] ) ? (string) $_SERVER['DOCUMENT_ROOT'] : '' );
	$user    = get_current_user();
	if ( function_exists( 'posix_geteuid' ) && function_exists( 'posix_getpwuid' ) ) {
		$pw   = posix_getpwuid( posix_geteuid() );
		$user = $pw ? $pw['name'] : (string) posix_geteuid();
	}
	$rows = array(
		'Plugin version'          => TXS_VERSION,
		'Publish state'           => var_export( get_option( 'txs_state' ), true ),
		'Adopted an empty folder' => get_option( 'txs_adopted' ) ? 'yes' : 'no',
		'ABSPATH'                 => ABSPATH . '  →  ' . var_export( realpath( ABSPATH ), true ),
		'DOCUMENT_ROOT'           => $docroot . '  →  ' . var_export( realpath( $docroot ), true ),
		'Publish target'          => $target . '  →  ' . var_export( realpath( $target ), true ),
		'Target is a symlink'     => is_link( $target ) ? 'yes → ' . readlink( $target ) : 'no',
		'Site root mode'          => txs_mode( ABSPATH ),
		'Target mode'             => txs_mode( $target ),
		'Target assets/ mode'     => txs_mode( $target . '/assets' ),
		'Site root writable'      => is_writable( ABSPATH ) ? 'yes' : 'no',
		'PHP runs as'             => $user,
		'open_basedir'            => ini_get( 'open_basedir' ) ? ini_get( 'open_basedir' ) : '(none)',
		'Server software'         => isset( $_SERVER['SERVER_SOFTWARE'] ) ? (string) $_SERVER['SERVER_SOFTWARE'] : '',
	);

	echo '<div class="wrap"><h1>TravelX Sliven — diagnostics</h1><table class="widefat striped" style="max-width:1100px"><tbody>';
	foreach ( $rows as $label => $value ) {
		echo '<tr><th style="width:220px">' . esc_html( $label ) . '</th><td><code>' . esc_html( (string) $value ) . '</code></td></tr>';
	}
	echo '</tbody></table>';

	echo '<h2>Contents of ' . esc_html( $target ) . '</h2><pre>' . esc_html( txs_listing( $target ) ) . '</pre>';
	if ( $docroot && realpath( $docroot ) !== realpath( ABSPATH ) ) {
		echo '<h2>Contents of ' . esc_html( $docroot . '/' . TXS_SLUG ) . '</h2><pre>' . esc_html( txs_listing( $docroot . '/' . TXS_SLUG ) ) . '</pre>';
	}

	echo '<h2>What the web server returns</h2><pre>';
	foreach ( array( '/sliven/', '/sliven/index.html', '/sliven/assets/og.jpg', '/wp-content/plugins/travelx-sliven/site/index.html' ) as $path ) {
		$r = wp_remote_get( home_url( $path ), array( 'timeout' => 10, 'redirection' => 0, 'sslverify' => false ) );
		$result = is_wp_error( $r ) ? 'error: ' . $r->get_error_message() : wp_remote_retrieve_response_code( $r ) . '  ' . strlen( wp_remote_retrieve_body( $r ) ) . ' B';
		echo esc_html( str_pad( $path, 56 ) . $result ) . "\n";
	}
	echo '</pre>';

	$htaccess = array_unique( array_filter( array( realpath( ABSPATH . '.htaccess' ), realpath( $docroot . '/.htaccess' ) ) ) );
	foreach ( $htaccess as $file ) {
		echo '<h2>' . esc_html( $file ) . '</h2><pre style="max-height:420px;overflow:auto;background:#fff;padding:8px;border:1px solid #ccd0d4">' . esc_html( (string) file_get_contents( $file ) ) . '</pre>';
	}

	echo '<form method="post">';
	wp_nonce_field( 'txs_republish' );
	submit_button( 'Republish /sliven/ now', 'secondary', 'txs_republish' );
	echo '</form></div>';
}
