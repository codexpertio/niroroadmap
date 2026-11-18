<?php
use NiroRoadmap\Helper\Sample_Data;
use PHPUnit\Framework\TestCase;

/**
 * Example content clean-up must see items in every status, trash included.
 */
class SampleDataTest extends TestCase {

	private $post_ids = array();

	private $term_ids = array();

	protected function tearDown(): void {
		foreach ( $this->post_ids as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $this->term_ids as $term ) {
			wp_delete_term( $term[0], $term[1] );
		}
	}

	private function item( $status, $example ) {
		$id               = wp_insert_post(
			array(
				'post_type'   => 'niroroadmap_item',
				'post_status' => 'publish',
				'post_title'  => 'Sample data test ' . wp_generate_password( 6, false, false ),
			)
		);
		$this->post_ids[] = $id;

		if ( $example ) {
			update_post_meta( $id, Sample_Data::FLAG, '1' );
		}
		if ( 'trash' === $status ) {
			wp_trash_post( $id );
		} elseif ( 'publish' !== $status ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => $status ) );
		}

		return $id;
	}

	public function test_example_items_in_every_status_are_found() {
		$ids = array();
		foreach ( array( 'publish', 'draft', 'pending', 'private', 'trash' ) as $status ) {
			$ids[ $status ] = $this->item( $status, true );
		}
		$real = $this->item( 'trash', false );

		$found = array_map( 'intval', Sample_Data::item_ids() );

		$this->assertSame( 'trash', get_post_status( $ids['trash'] ) );
		foreach ( $ids as $status => $id ) {
			$this->assertContains( $id, $found, "The {$status} example item is missed." );
		}
		$this->assertNotContains( $real, $found, 'A real item is never an example.' );
	}

	public function test_a_term_only_a_trashed_item_uses_is_still_in_use() {
		$term             = wp_insert_term( 'Sample data test ' . wp_generate_password( 6, false, false ), 'niroroadmap_product' );
		$this->term_ids[] = array( $term['term_id'], 'niroroadmap_product' );
		$item             = $this->item( 'publish', false );
		wp_set_object_terms( $item, array( (int) $term['term_id'] ), 'niroroadmap_product' );
		wp_trash_post( $item );

		$in_use = new ReflectionMethod( Sample_Data::class, 'term_in_use' );
		$in_use->setAccessible( true );

		$this->assertTrue( $in_use->invoke( null, get_term( $term['term_id'], 'niroroadmap_product' ) ), 'Restoring the item would lose its product.' );
	}
}
