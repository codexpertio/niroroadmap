<?php
namespace NiroRoadmap\Bootstrap;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Controller\Front\Legacy_Urls;

/**
 * One-time data migrations for sites that update the plugin.
 */
class Migrator {

	/**
	 * Raise when a new migration is added.
	 */
	const VERSION = '1';

	const OPTION = 'niroroadmap_migration_version';

	/**
	 * Counts from the last link migration, shown once to the site owner.
	 */
	const REPORT = 'niroroadmap_links_migrated';

	const NAV_TYPES = array( 'wp_navigation', 'wp_template', 'wp_template_part' );

	/**
	 * Links left alone because a shop term may own the same address.
	 *
	 * @var int
	 */
	private static $skipped = 0;

	/**
	 * Run the pending migrations once. Hooked on `init` after the taxonomies are registered.
	 */
	public static function maybe_run() {
		if ( self::VERSION === get_option( self::OPTION ) || get_transient( 'niroroadmap_migrating' ) ) {
			return;
		}

		set_transient( 'niroroadmap_migrating', 1, 5 * MINUTE_IN_SECONDS );
		self::$skipped = 0;

		$report = array(
			'menus'      => self::migrate_menu_links(),
			'navigation' => self::migrate_navigation(),
			'skipped'    => self::$skipped,
		);

		update_option( self::REPORT, $report, false );
		update_option( self::OPTION, self::VERSION );
		delete_transient( 'niroroadmap_migrating' );

		/**
		 * Fires once after the roadmap archive URLs changed, so caches and SEO plugins can purge.
		 *
		 * @param array $bases Old archive base => current base.
		 */
		do_action( 'niroroadmap_archive_urls_changed', Legacy_Urls::bases() );
	}

	/**
	 * Custom links in classic menus keep their URL as text.
	 *
	 * @return int Menu items changed.
	 */
	public static function migrate_menu_links() {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-time scan for a LIKE no API offers.
			"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_url' AND ( meta_value LIKE '%/product-cat/%' OR meta_value LIKE '%/product-brand/%' OR meta_value LIKE '%/product-tag/%' )"
		);

		$changed = 0;

		foreach ( $rows as $row ) {
			$url = Legacy_Urls::replace( $row->meta_value, self::$skipped );

			if ( $url !== $row->meta_value ) {
				update_post_meta( (int) $row->post_id, '_menu_item_url', esc_url_raw( $url ) );
				++$changed;
			}
		}

		return $changed;
	}

	/**
	 * Navigation links in block navigation menus, templates and template parts.
	 *
	 * @return int Posts changed.
	 */
	public static function migrate_navigation() {
		$changed = 0;

		$ids = get_posts(
			array(
				'post_type'      => self::NAV_TYPES,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				's'              => 'wp:navigation-',
			)
		);

		foreach ( $ids as $id ) {
			$content = get_post_field( 'post_content', $id, 'raw' );
			$updated = self::migrate_navigation_blocks( $content );

			if ( $updated !== $content ) {
				self::update_content( $id, $updated );
				++$changed;
			}
		}

		return $changed;
	}

	/**
	 * Point roadmap navigation links in block markup at their current URLs.
	 *
	 * @param string $content Block markup.
	 * @return string
	 */
	public static function migrate_navigation_blocks( $content ) {
		return preg_replace_callback(
			'#(<!--\s+wp:(?:core/)?navigation-(?:link|submenu)\s+)(\{.*?\})(\s+/?-->)#s',
			static function ( $m ) {
				$attrs = json_decode( $m[2], true );

				if ( ! is_array( $attrs ) || empty( $attrs['url'] ) ) {
					return $m[0];
				}

				$url = $attrs['url'];

				if ( isset( $attrs['kind'], $attrs['type'], $attrs['id'] ) && 'taxonomy' === $attrs['kind'] && in_array( $attrs['type'], Legacy_Urls::SLUGS, true ) ) {
					$link = get_term_link( (int) $attrs['id'], $attrs['type'] );
					$url  = is_string( $link ) ? $link : $url;
				} else {
					$url = Legacy_Urls::replace( $url, self::$skipped );
				}

				if ( $url === $attrs['url'] ) {
					return $m[0];
				}

				$attrs['url'] = $url;

				return $m[1] . serialize_block_attributes( $attrs ) . $m[3];
			},
			$content
		);
	}

	/**
	 * Save new post content with a revision, without kses touching the rest of the markup.
	 *
	 * @param int    $id      Post ID.
	 * @param string $content New content.
	 */
	public static function update_content( $id, $content ) {
		$kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );

		if ( $kses ) {
			kses_remove_filters();
		}

		wp_update_post(
			array(
				'ID'           => (int) $id,
				'post_content' => wp_slash( $content ),
			)
		);

		if ( $kses ) {
			kses_init_filters();
		}
	}
}
