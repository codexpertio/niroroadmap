<?php
namespace NiroRoadmap\Controller\Common;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Model\Status_Log;
use NiroRoadmap\Trait\Hook;

/**
 * Keeps the status history. Hooking the term relationship itself means every way of changing a
 * status is covered: the editor, Quick and bulk edit, drag and drop (REST), imports and code.
 */
class Status_History {

	use Hook;

	/**
	 * Constructor to add all hooks.
	 */
	public function __construct() {
		$this->action( 'set_object_terms', array( $this, 'log_change' ), 10, 6 );
		$this->action( 'deleted_post', array( $this, 'delete_history' ), 10, 2 );
	}

	/**
	 * Log an item's move from one status to another.
	 *
	 * @param int    $object_id  Item ID.
	 * @param array  $terms      Terms as passed in.
	 * @param array  $tt_ids     Term taxonomy IDs passed in.
	 * @param string $taxonomy   Taxonomy.
	 * @param bool   $append     Whether the terms were added to the existing ones.
	 * @param array  $old_tt_ids Term taxonomy IDs the item had before.
	 */
	public function log_change( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( 'niroroadmap_status' !== $taxonomy || 'niroroadmap_item' !== get_post_type( $object_id ) ) {
			return;
		}

		// What the item has now. Asked of the database, because with $append the IDs passed in
		// are only the ones being added.
		$now = wp_get_object_terms( $object_id, 'niroroadmap_status', array( 'fields' => 'tt_ids' ) );
		$now = is_wp_error( $now ) ? array() : array_map( 'intval', $now );
		$was = array_map( 'intval', (array) $old_tt_ids );

		sort( $now );
		sort( $was );

		if ( $now === $was ) {
			return;
		}

		Status_Log::record( $object_id, $this->term( $was ), $this->term( $now ), get_current_user_id() );
	}

	/**
	 * The first status of a set of term taxonomy IDs. An item has one status in practice.
	 *
	 * @param int[] $tt_ids Term taxonomy IDs.
	 * @return \WP_Term|null
	 */
	private function term( $tt_ids ) {
		foreach ( $tt_ids as $tt_id ) {
			$term = get_term_by( 'term_taxonomy_id', $tt_id, 'niroroadmap_status' );

			if ( $term ) {
				return $term;
			}
		}

		return null;
	}

	/**
	 * Drop the history of an item that was deleted for good.
	 */
	public function delete_history( $post_id, $post ) {
		if ( $post instanceof \WP_Post && 'niroroadmap_item' === $post->post_type ) {
			Status_Log::delete_for_item( $post_id );
		}
	}
}
