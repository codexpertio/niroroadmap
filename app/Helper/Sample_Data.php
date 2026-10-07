<?php
namespace NiroRoadmap\Helper;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Bootstrap\Installer;
use NiroRoadmap\Model\Fields;

/**
 * Example content for the Getting Started screen: enough of a roadmap to see every part working.
 *
 * Everything this class adds is flagged, so removing the examples never touches the site owner's
 * own items, products or tags. Statuses and the Roadmap page are the plugin's defaults, not examples,
 * so they are only ever created, never removed.
 */
class Sample_Data {

	const FLAG = '_niroroadmap_sample';

	/**
	 * Make sure the building blocks exist, then add the example items.
	 *
	 * @return int Number of items added.
	 */
	public static function create() {
		if ( self::item_ids() ) {
			return 0;
		}

		Installer::ensure_statuses();
		Installer::ensure_roadmap_page();

		$statuses = self::statuses();

		if ( ! $statuses ) {
			return 0;
		}

		$products = array();
		foreach ( array( __( 'Web App', 'niroroadmap' ), __( 'Mobile App', 'niroroadmap' ) ) as $name ) {
			$products[ $name ] = self::term( $name, 'niroroadmap_product' );
		}

		$quarter = static function ( $offset ) {
			$index = (int) gmdate( 'Y' ) * 4 + (int) floor( ( (int) gmdate( 'n' ) - 1 ) / 3 ) + $offset;

			return 'Q' . ( $index % 4 + 1 ) . ' ' . intdiv( $index, 4 );
		};

		$web    = __( 'Web App', 'niroroadmap' );
		$mobile = __( 'Mobile App', 'niroroadmap' );

		// Status index (0 = first column), product, tags, votes, details.
		$items = array(
			array( __( 'Dark mode', 'niroroadmap' ), __( 'Many of you work late. Add a dark theme that follows your system setting.', 'niroroadmap' ), 0, $web, array( __( 'Design', 'niroroadmap' ) ), array( 42, 2 ), array() ),
			array( __( 'Slack integration', 'niroroadmap' ), __( 'Post a message to a Slack channel when an item changes status.', 'niroroadmap' ), 0, $web, array( __( 'Integrations', 'niroroadmap' ) ), array( 31, 1 ), array() ),
			array( __( 'Two-factor authentication', 'niroroadmap' ), __( 'Protect your account with a one-time code from an authenticator app.', 'niroroadmap' ), 1, $web, array( __( 'Security', 'niroroadmap' ) ), array( 57, 0 ), array( 'target' => $quarter( 1 ), 'effort' => 'M', 'pinned' => '1' ) ),
			array( __( 'Offline mode', 'niroroadmap' ), __( 'Keep working without a connection. Your changes sync when you are back online.', 'niroroadmap' ), 1, $mobile, array( __( 'Performance', 'niroroadmap' ) ), array( 24, 2 ), array( 'target' => $quarter( 2 ), 'effort' => 'L' ) ),
			array( __( 'Faster search', 'niroroadmap' ), __( 'Search across everything in under a second, even on large accounts.', 'niroroadmap' ), 2, $web, array( __( 'Performance', 'niroroadmap' ) ), array( 29, 0 ), array( 'target' => $quarter( 0 ), 'effort' => 'M' ) ),
			array( __( 'Push notifications', 'niroroadmap' ), __( 'Get a notification on your phone when someone replies or an item is updated.', 'niroroadmap' ), 2, $mobile, array( __( 'Integrations', 'niroroadmap' ) ), array( 22, 1 ), array( 'target' => $quarter( 1 ), 'effort' => 'S' ) ),
			array( __( 'CSV import', 'niroroadmap' ), __( 'Bring your existing data in from a spreadsheet, with a preview before anything is saved.', 'niroroadmap' ), 3, $web, array( __( 'Integrations', 'niroroadmap' ) ), array( 45, 1 ), array( 'target' => $quarter( -1 ), 'release' => gmdate( 'Y-m-d', time() - 20 * DAY_IN_SECONDS ), 'version' => '1.1.0', 'effort' => 'M' ) ),
		);

		$added = 0;

		foreach ( $items as $position => $item ) {
			list( $title, $content, $status, $product, $tags, $votes, $fields ) = $item;

			$id = wp_insert_post(
				array(
					'post_type'    => 'niroroadmap_item',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => $content,
					'menu_order'   => $position,
				)
			);

			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}

			update_post_meta( $id, self::FLAG, '1' );
			update_post_meta( $id, 'upvote', $votes[0] );
			update_post_meta( $id, 'downvote', $votes[1] );

			wp_set_object_terms( $id, (int) $statuses[ min( $status, count( $statuses ) - 1 ) ]->term_id, 'niroroadmap_status' );

			if ( ! empty( $products[ $product ] ) ) {
				wp_set_object_terms( $id, array( $products[ $product ] ), 'niroroadmap_product' );
			}

			$tag_ids = array();
			foreach ( $tags as $tag ) {
				$tag_ids[] = self::term( $tag, 'niroroadmap_tag' );
			}
			wp_set_object_terms( $id, array_filter( $tag_ids ), 'niroroadmap_tag' );

			if ( $fields ) {
				$clean = Fields::sanitize( $fields );
				Fields::save( $id, $clean['values'] );
			}

			++$added;
		}

		return $added;
	}

	/**
	 * Remove the example items, and the products and tags added for them, unless real items now use them.
	 */
	public static function remove() {
		foreach ( self::item_ids() as $id ) {
			wp_delete_post( $id, true );
		}

		foreach ( array( 'niroroadmap_product', 'niroroadmap_tag' ) as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'meta_key'   => self::FLAG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Few terms, admin-only.
					'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);

			if ( is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				// A visitor's or editor's own item may have been put under it since.
				if ( ! self::term_in_use( $term ) ) {
					wp_delete_term( $term->term_id, $taxonomy );
				}
			}
		}
	}

	/**
	 * @return int[] IDs of the example items.
	 */
	public static function item_ids() {
		return get_posts(
			array(
				'post_type'      => 'niroroadmap_item',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::FLAG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small, admin-only lookup.
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * The site's statuses in board order.
	 *
	 * @return \WP_Term[]
	 */
	private static function statuses() {
		$statuses = get_terms(
			array(
				'taxonomy'   => 'niroroadmap_status',
				'hide_empty' => false,
				'meta_key'   => 'menu_order', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Few terms, admin-only.
				'orderby'    => 'meta_value_num',
			)
		);

		if ( is_wp_error( $statuses ) || ! $statuses ) {
			$statuses = get_terms( array( 'taxonomy' => 'niroroadmap_status', 'hide_empty' => false ) );
		}

		return is_wp_error( $statuses ) ? array() : $statuses;
	}

	/**
	 * Find a term by name, or add it (flagged as an example). One the site already has is reused and
	 * left unflagged, so removing the examples never deletes it.
	 *
	 * @return int Term ID, 0 on failure.
	 */
	private static function term( $name, $taxonomy ) {
		$existing = term_exists( $name, $taxonomy );

		if ( $existing ) {
			return (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
		}

		$term = wp_insert_term( $name, $taxonomy );

		if ( is_wp_error( $term ) ) {
			return 0;
		}

		update_term_meta( $term['term_id'], self::FLAG, '1' );

		return (int) $term['term_id'];
	}

	private static function term_in_use( $term ) {
		$posts = get_posts(
			array(
				'post_type'      => 'niroroadmap_item',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One-off, admin-only.
					array(
						'taxonomy' => $term->taxonomy,
						'terms'    => $term->term_id,
					),
				),
			)
		);

		return (bool) $posts;
	}
}
