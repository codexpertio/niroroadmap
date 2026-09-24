<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$deletable_options = [ 'niroroadmap_activated', 'niroroadmap_db_version', 'niroroadmap_page_id', 'niroroadmap_statuses_seeded' ];
foreach ( $deletable_options as $option ) {
    delete_option( $option );
}