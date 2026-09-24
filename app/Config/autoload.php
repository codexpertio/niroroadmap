<?php
// `return`, not `exit`: this file is loaded by Composer's autoloader, so dev tools (phpunit, phpcs)
// include it outside WordPress too, and `exit` would silently stop them with a success code.
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// Dynamically includes all PHP files in the app/Config directory, excluding the curren file
foreach ( glob( dirname( __DIR__ ) . '/Config/*.php' ) as $config_file ) {
	if ( basename( $config_file ) === 'autoload.php' ) {
		continue;
	}

	require_once $config_file;
}
