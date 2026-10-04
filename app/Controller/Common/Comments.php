<?php
namespace NiroRoadmap\Controller\Common;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Trait\Hook;

class Comments {

	use Hook;

	/**
	 * Constructor to add all hooks.
	 */
	public function __construct() {
		$this->action( 'set_object_terms', array( $this, 'maybe_close_comments' ), 10, 4 );
	}

	/**
	 * Close an item's comments when it moves to the Completed status (optional setting).
	 *
	 * Comments are never reopened by moving the item back: there's no telling whether someone
	 * closed them by hand in between.
	 *
	 * @param int    $object_id Item ID.
	 * @param array  $terms     Terms as passed in.
	 * @param array  $tt_ids    Term taxonomy IDs now set on the item.
	 * @param string $taxonomy  Taxonomy.
	 */
	public function maybe_close_comments( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( 'niroroadmap_status' !== $taxonomy || ! niroroadmap_get_setting( 'comments_auto_close' ) || ! $tt_ids ) {
			return;
		}

		$item = get_post( $object_id );

		if ( ! $item || 'niroroadmap_item' !== $item->post_type || 'open' !== $item->comment_status ) {
			return;
		}

		/**
		 * Filters the slugs of the statuses that count as "Completed".
		 *
		 * @param string[] $slugs Default `completed`, the status created on install.
		 */
		$slugs = (array) apply_filters( 'niroroadmap_completed_status_slugs', array( 'completed' ) );

		$done = get_terms(
			array(
				'taxonomy'         => 'niroroadmap_status',
				'term_taxonomy_id' => array_map( 'intval', $tt_ids ),
				'slug'             => $slugs,
				'hide_empty'       => false,
				'fields'           => 'ids',
			)
		);

		if ( ! is_wp_error( $done ) && $done ) {
			wp_update_post(
				array(
					'ID'             => $object_id,
					'comment_status' => 'closed',
				)
			);
		}
	}
}
