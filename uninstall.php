<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$deletable_options = [ 'niroroadmap_activated', 'niroroadmap_db_version', 'niroroadmap_page_id', 'niroroadmap_statuses_seeded', 'niroroadmap_getting_started_done' ];
foreach ( $deletable_options as $option ) {
    delete_option( $option );
}

// Roadmap data is kept unless the site owner turned on "Delete all data" in Settings -> Advanced.
$settings = get_option( 'niroroadmap_settings' );

if ( ! is_array( $settings ) || empty( $settings['delete_on_uninstall'] ) ) {
    return;
}

// The plugin isn't loaded during uninstall, so its post type and taxonomies aren't registered.
// Terms can only be deleted through a registered taxonomy.
$taxonomies = [ 'niroroadmap_status', 'niroroadmap_product', 'niroroadmap_tag' ];
foreach ( $taxonomies as $taxonomy ) {
    register_taxonomy( $taxonomy, 'niroroadmap_item' );
}

// Force-deleting a post also deletes its meta (votes) and comments.
$item_ids = get_posts(
    [
        'post_type'      => 'niroroadmap_item',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]
);
foreach ( $item_ids as $item_id ) {
    wp_delete_post( $item_id, true );
}

foreach ( $taxonomies as $taxonomy ) {
    $terms = get_terms(
        [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'fields'     => 'ids',
        ]
    );

    if ( is_wp_error( $terms ) ) {
        continue;
    }

    foreach ( $terms as $term_id ) {
        wp_delete_term( $term_id, $taxonomy );
    }
}

// The per-voter vote rows (hashes only) go with the items.
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}niroroadmap_votes" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Our own table, on uninstall.
delete_option( 'niroroadmap_schema_version' );

// And the status history.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}niroroadmap_status_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Our own table, on uninstall.
delete_option( 'niroroadmap_status_log_schema' );

delete_option( 'niroroadmap_settings' );
