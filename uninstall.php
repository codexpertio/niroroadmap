<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$deletable_options = [ 'niroroadmap_activated', 'niroroadmap_db_version' ];
foreach ( $deletable_options as $option ) {
    delete_option( $option );
}