<?php
/**
 * Integration test bootstrap.
 *
 * These tests run against a real WordPress install and its database (vote de-duplication relies
 * on unique indexes, which can't be mocked). They create their own posts and remove them again,
 * but use a development site, never a production one.
 *
 * Point WP_LOAD_PATH at the install's wp-load.php, or leave it unset when the plugin lives in
 * wp-content/plugins/ of the install.
 *
 * Run with `composer test`. WordPress has to load before Composer's autoloader: the plugin's
 * helper functions file exits when ABSPATH is missing, which would silently end PHPUnit. That
 * is why this file is also the `auto_prepend_file` in that script.
 */

if ( defined( 'ABSPATH' ) ) {
	return;
}

$wp_load = getenv( 'WP_LOAD_PATH' ) ? getenv( 'WP_LOAD_PATH' ) : dirname( __DIR__, 4 ) . '/wp-load.php';

if ( ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php not found at {$wp_load}. Set WP_LOAD_PATH.\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';

require_once $wp_load;
