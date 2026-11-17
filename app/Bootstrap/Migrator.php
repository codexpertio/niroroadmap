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

	const NAV_TYPES = array( 'wp_navigation', 'wp_template', 'wp_template_part' );

	const WIDGETS = array( 'widget_block', 'widget_text', 'widget_custom_html' );

	/**
	 * Last post ID the background link migration has handled.
	 */
	const CURSOR = 'niroroadmap_links_cursor';

	const EVENT = 'niroroadmap_migrate_links';

	const BATCH = 100;

	/**
	 * Run the pending migrations once. Hooked on `init` after the taxonomies are registered.
	 */
	public static function maybe_run() {
		if ( self::VERSION === get_option( self::OPTION ) ) {
			if ( false !== get_option( self::CURSOR ) && ! wp_next_scheduled( self::EVENT ) ) {
				wp_schedule_single_event( time(), self::EVENT );
			}
			return;
		}

		if ( get_transient( 'niroroadmap_migrating' ) ) {
			return;
		}

		set_transient( 'niroroadmap_migrating', 1, 5 * MINUTE_IN_SECONDS );

		self::migrate_menu_links();
		self::migrate_navigation();
		self::update_widgets();

		update_option( self::CURSOR, 0, false );
		update_option( self::OPTION, self::VERSION );
		delete_transient( 'niroroadmap_migrating' );

		wp_schedule_single_event( time(), self::EVENT );
	}

	/**
	 * One background batch of posts; reschedules itself until every post is done.
	 */
	public static function run_batch() {
		$after = get_option( self::CURSOR );

		if ( false === $after ) {
			return;
		}

		foreach ( self::find_posts( (int) $after ) as $id ) {
			$after   = (int) $id;
			$content = get_post_field( 'post_content', $after, 'raw' );
			$updated = Legacy_Urls::replace( $content );

			if ( $updated !== $content ) {
				self::update_content( $after, $updated );
			}
		}

		if ( self::find_posts( (int) $after, 1 ) ) {
			update_option( self::CURSOR, (int) $after, false );
			wp_schedule_single_event( time(), self::EVENT );
			return;
		}

		delete_option( self::CURSOR );

		/**
		 * Fires once every stored link points at the new archive URLs, so caches and SEO plugins can purge.
		 *
		 * @param array $bases Old archive base => current base.
		 */
		do_action( 'niroroadmap_archive_urls_changed', Legacy_Urls::bases() );
	}

	/**
	 * IDs of posts after `$after` whose content may hold an old roadmap archive URL.
	 *
	 * @param int $after Last post ID already handled.
	 * @param int $limit Max IDs.
	 * @return int[]
	 */
	public static function find_posts( $after = 0, $limit = self::BATCH ) {
		global $wpdb;

		$like  = array();
		$where = array();

		foreach ( array_keys( Legacy_Urls::SLUGS ) as $base ) {
			$where[] = 'post_content LIKE %s';
			$like[]  = '%' . $wpdb->esc_like( '/' . $base . '/' ) . '%';
		}

		$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ( 'revision', 'nav_menu_item' ) AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' ) AND ID > %d AND ( " . implode( ' OR ', $where ) . ' ) ORDER BY ID ASC LIMIT %d';

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql, array_merge( array( (int) $after ), $like, array( (int) $limit ) ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- $sql holds only placeholders and the table name.
	}

	/**
	 * Old roadmap links in text, block and custom HTML widgets.
	 *
	 * @return int Widgets changed.
	 */
	public static function update_widgets() {
		$changed = 0;

		foreach ( self::WIDGETS as $option ) {
			$widgets = get_option( $option );

			if ( ! is_array( $widgets ) ) {
				continue;
			}

			$before = $changed;

			foreach ( $widgets as $key => $widget ) {
				foreach ( array( 'content', 'text' ) as $field ) {
					if ( is_array( $widget ) && isset( $widget[ $field ] ) && is_string( $widget[ $field ] ) ) {
						$new = Legacy_Urls::replace( $widget[ $field ] );

						if ( $new !== $widget[ $field ] ) {
							$widgets[ $key ][ $field ] = $new;
							++$changed;
						}
					}
				}
			}

			if ( $changed > $before ) {
				update_option( $option, $widgets );
			}
		}

		return $changed;
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
			$url = Legacy_Urls::replace( $row->meta_value );

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
					$url = Legacy_Urls::replace( $url );
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
