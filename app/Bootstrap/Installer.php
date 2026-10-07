<?php
namespace NiroRoadmap\Bootstrap;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Helper\Settings;
use NiroRoadmap\Model\Database;
use NiroRoadmap\Model\Status_Log;
use NiroRoadmap\Model\Vote;

class Installer {

	/**
	 * Run installation routines.
	 */
	public static function install() {
		$installer = new self();

		if ( ! $installer->is_database_up_to_date() ) {
			$installer->update_db_version();
		}

		self::maybe_upgrade();

		$installer->create_roadmap_page();

		// Take the person who activated the plugin to the Getting Started screen, once.
		set_transient( 'niroroadmap_activation_redirect', 1, 60 );

		// Statuses need the taxonomy, which isn't registered yet during activation.
		// Flag them here; `seed_statuses()` runs on the next `init`. Only ever once per site.
		if ( ! get_option( 'niroroadmap_statuses_seeded' ) ) {
			update_option( 'niroroadmap_statuses_seeded', 'pending' );
		}
	}

	/**
	 * Bring the database up to date after a plugin update. Cheap when nothing is needed.
	 */
	public static function maybe_upgrade() {
		Vote::maybe_create_table();
		Status_Log::maybe_create_table();
	}

	/**
	 * Make sure the board has its columns, adding the defaults when there are none. Safe to call
	 * more than once. Used by the Getting Started screen when it fills the board with examples.
	 */
	public static function ensure_statuses() {
		if ( ! taxonomy_exists( 'niroroadmap_status' ) ) {
			return;
		}

		update_option( 'niroroadmap_statuses_seeded', 'pending' );
		self::seed_statuses();
	}

	/**
	 * Make sure there is a Roadmap page, creating or finding one as on activation.
	 */
	public static function ensure_roadmap_page() {
		( new self() )->create_roadmap_page();
	}

	/**
	 * Add the default statuses on a fresh install. Runs on `init`, after the taxonomy is registered.
	 * Existing statuses are never touched, and deleted defaults are never re-added.
	 */
	public static function seed_statuses() {
		if ( 'pending' !== get_option( 'niroroadmap_statuses_seeded' ) || ! taxonomy_exists( 'niroroadmap_status' ) ) {
			return;
		}

		update_option( 'niroroadmap_statuses_seeded', 'yes' );

		if ( wp_count_terms( array( 'taxonomy' => 'niroroadmap_status', 'hide_empty' => false ) ) ) {
			return;
		}

		$statuses = array(
			__( 'Under Review', 'niroroadmap' ) => '#a855f7',
			__( 'Planned', 'niroroadmap' )      => '#3b82f6',
			__( 'In Progress', 'niroroadmap' )  => '#f59e0b',
			__( 'Completed', 'niroroadmap' )    => '#22c55e',
		);

		$order = 0;
		foreach ( $statuses as $name => $color ) {
			$term = wp_insert_term( $name, 'niroroadmap_status' );

			if ( ! is_wp_error( $term ) ) {
				update_term_meta( $term['term_id'], 'color', $color );
				update_term_meta( $term['term_id'], 'menu_order', $order++ );
			}
		}
	}

	/**
	 * Create the public roadmap page, unless one already exists.
	 */
	protected function create_roadmap_page() {
		// Falls back to the old hidden `niroroadmap_page_id` option for sites installed before Settings existed.
		$page_id = (int) niroroadmap_get_setting( 'roadmap_page' );

		// Created before. Also covers a trashed page, so we don't bring back a page the user removed.
		if ( $page_id && get_post( $page_id ) ) {
			return;
		}

		$page_id = $this->find_roadmap_page();

		if ( ! $page_id ) {
			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => __( 'Roadmap', 'niroroadmap' ),
					'post_content' => "<!-- wp:shortcode -->\n[niroroadmap]\n<!-- /wp:shortcode -->",
				)
			);
		}

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			Settings::update( 'roadmap_page', (int) $page_id );
		}
	}

	/**
	 * Find a page that already shows the roadmap (shortcode or block).
	 *
	 * `has_shortcode()` can't be used here: it only matches registered shortcodes, and ours isn't
	 * registered during activation. SQL narrows down the candidates, then the same regex
	 * `has_shortcode()` uses confirms them (so `[[roadmap]]` and `[roadmap_x]` don't count).
	 *
	 * @return int Page ID, or 0 if none.
	 */
	protected function find_roadmap_page() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off lookup during activation; no user input.
		$candidates = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'page'
			AND post_status IN ( 'publish', 'future', 'draft', 'pending', 'private' )
			AND ( post_content LIKE '%[niroroadmap%' OR post_content LIKE '%[roadmap%' OR post_content LIKE '%<!-- wp:niroroadmap/roadmap%' )
			ORDER BY ID ASC"
		);

		$regex = '/' . get_shortcode_regex( array( 'niroroadmap', 'roadmap' ) ) . '/';

		foreach ( $candidates as $page_id ) {
			$page = get_post( $page_id );

			if ( ! $page ) {
				continue;
			}

			if ( has_block( 'niroroadmap/roadmap', $page ) ) {
				return (int) $page_id;
			}

			preg_match_all( $regex, $page->post_content, $matches, PREG_SET_ORDER );
			foreach ( $matches as $match ) {
				// $match[1] / $match[6] hold the extra brackets of an escaped `[[shortcode]]`.
				if ( '[' !== $match[1] || ']' !== $match[6] ) {
					return (int) $page_id;
				}
			}
		}

		return 0;
	}

	/**
	 * Check if the database is up to date.
	 *
	 * @return bool
	 */
	protected function is_database_up_to_date() {
		$installed_ver = get_option( 'niroroadmap_db_version' );
		return version_compare( $installed_ver, NIROROADMAP_VERSION, '=' );
	}

	/**
	 * Update or add the database version to the options table.
	 */
	protected function update_db_version() {
		update_option( 'niroroadmap_db_version', NIROROADMAP_VERSION );
	}
}
