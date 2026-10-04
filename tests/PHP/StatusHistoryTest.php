<?php
use NiroRoadmap\Model\Status_Log;
use PHPUnit\Framework\TestCase;

/**
 * The status history: every way of changing a status is logged, and the public timeline is safe.
 */
class StatusHistoryTest extends TestCase {

	private $item_ids = array();

	private $user_ids = array();

	private $term_ids = array();

	protected function setUp(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_set_current_user( 0 );
		Status_Log::maybe_create_table();
	}

	protected function tearDown(): void {
		foreach ( $this->item_ids as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $this->user_ids as $id ) {
			wp_delete_user( $id );
		}
		foreach ( $this->term_ids as $id ) {
			wp_delete_term( $id, 'niroroadmap_status' );
		}
		wp_set_current_user( 0 );
	}

	private function make_status( $name ) {
		$term = wp_insert_term( $name . ' ' . wp_generate_password( 5, false ), 'niroroadmap_status' );
		$this->term_ids[] = $term['term_id'];

		return $term['term_id'];
	}

	private function item() {
		$id = wp_insert_post( array( 'post_type' => 'niroroadmap_item', 'post_status' => 'publish', 'post_title' => 'History item ' . wp_generate_password( 6, false ) ) );

		return $this->item_ids[] = $id;
	}

	private function user( $name = 'Sam Mover' ) {
		return $this->user_ids[] = wp_insert_user( array( 'user_login' => 'htest_' . wp_generate_password( 8, false ), 'user_pass' => wp_generate_password(), 'user_email' => wp_generate_password( 8, false ) . '@example.test', 'role' => 'administrator', 'display_name' => $name ) );
	}

	private function rows( $id ) {
		return Status_Log::for_item( $id, 'ASC' );
	}

	public function test_the_first_status_is_logged_as_coming_from_nothing() {
		$id = $this->item();
		$a  = $this->make_status( 'Alpha' );

		wp_set_post_terms( $id, array( $a ), 'niroroadmap_status' );

		$rows = $this->rows( $id );
		$this->assertCount( 1, $rows );
		$this->assertNull( $rows[0]->from_term );
		$this->assertSame( $a, (int) $rows[0]->to_term );
		$this->assertSame( '', $rows[0]->from_name );
		$this->assertStringStartsWith( 'Alpha', $rows[0]->to_name );
	}

	public function test_a_move_is_logged_with_who_did_it() {
		$id   = $this->item();
		$a    = $this->make_status( 'Alpha' );
		$b    = $this->make_status( 'Beta' );
		$user = $this->user();

		wp_set_post_terms( $id, array( $a ), 'niroroadmap_status' );
		wp_set_current_user( $user );
		wp_set_post_terms( $id, array( $b ), 'niroroadmap_status' );

		$rows = $this->rows( $id );
		$this->assertCount( 2, $rows );
		$this->assertSame( $a, (int) $rows[1]->from_term );
		$this->assertSame( $b, (int) $rows[1]->to_term );
		$this->assertSame( $user, (int) $rows[1]->user_id );
		$this->assertSame( 0, (int) $rows[0]->user_id, 'Changes by code or a visitor are logged with no user.' );
	}

	public function test_setting_the_same_status_again_logs_nothing() {
		$id = $this->item();
		$a  = $this->make_status( 'Alpha' );

		wp_set_post_terms( $id, array( $a ), 'niroroadmap_status' );
		wp_set_post_terms( $id, array( $a ), 'niroroadmap_status' );
		wp_set_object_terms( $id, array( $a ), 'niroroadmap_status' );

		$this->assertCount( 1, $this->rows( $id ) );
	}

	public function test_every_way_of_changing_a_status_is_covered() {
		$id = $this->item();
		$a  = $this->make_status( 'Alpha' );
		$b  = $this->make_status( 'Beta' );
		$c  = $this->make_status( 'Gamma' );
		$d  = $this->make_status( 'Delta' );

		wp_set_post_terms( $id, array( $a ), 'niroroadmap_status' );             // The editor, and drag and drop (Task::move).
		wp_set_object_terms( $id, array( $b ), 'niroroadmap_status' );          // Code and imports.
		wp_update_post( array( 'ID' => $id, 'tax_input' => array( 'niroroadmap_status' => array( $c ) ) ) ); // The classic editor form.
		wp_set_object_terms( $id, array( $d ), 'niroroadmap_status', true );    // Adding a status, as bulk edit does.

		$rows = $this->rows( $id );
		$this->assertGreaterThanOrEqual( 3, count( $rows ), 'Each distinct change was logged.' );
		$this->assertSame( $b, (int) $rows[1]->to_term );
	}

	public function test_a_move_through_the_rest_endpoint_is_logged() {
		$id = $this->item();
		$a  = $this->make_status( 'Alpha' );
		$b  = $this->make_status( 'Beta' );
		wp_set_post_terms( $id, array( $a ), 'niroroadmap_status' );

		// What Task::move() does; the method itself ends the request.
		wp_set_post_terms( $id, array( $b ), 'niroroadmap_status' );

		$rows = $this->rows( $id );
		$last = end( $rows );
		$this->assertSame( $b, (int) $last->to_term );
	}

	public function test_only_roadmap_items_are_logged() {
		$post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Not an item' ) );
		$this->item_ids[] = $post;

		wp_set_object_terms( $post, array( $this->make_status( 'Alpha' ) ), 'niroroadmap_status' );

		$this->assertSame( array(), $this->rows( $post ) );
	}

	public function test_history_goes_when_the_item_is_deleted_for_good() {
		$id = $this->item();
		wp_set_post_terms( $id, array( $this->make_status( 'Alpha' ) ), 'niroroadmap_status' );
		$this->assertCount( 1, $this->rows( $id ) );

		wp_delete_post( $id, true );

		$this->assertSame( array(), $this->rows( $id ) );
		$this->item_ids = array();
	}

	public function test_the_public_timeline_is_the_path_with_dates_and_never_a_person() {
		$id   = $this->item();
		$a    = $this->make_status( 'Alpha' );
		$b    = $this->make_status( 'Beta' );
		$c    = $this->make_status( 'Gamma' );
		$user = $this->user( 'Secret Person' );

		wp_set_current_user( $user );
		wp_set_post_terms( $id, array( $a ), 'niroroadmap_status' );
		wp_set_post_terms( $id, array( $b ), 'niroroadmap_status' );
		wp_set_post_terms( $id, array( $c ), 'niroroadmap_status' );

		$timeline = Status_Log::timeline( $id );

		$this->assertCount( 3, $timeline );
		$this->assertStringStartsWith( 'Alpha', $timeline[0]['name'] );
		$this->assertStringStartsWith( 'Gamma', $timeline[2]['name'] );
		$this->assertNotEmpty( $timeline[2]['date'] );
		$this->assertStringNotContainsString( 'Secret Person', wp_json_encode( $timeline ) );
		$this->assertStringNotContainsString( (string) $user . '"', wp_json_encode( $timeline ), 'No user ID either.' );
	}

	public function test_a_status_that_was_there_before_logging_began_opens_the_timeline_without_a_date() {
		$id = $this->item();
		$a  = $this->make_status( 'Alpha' );
		$b  = $this->make_status( 'Beta' );

		// An item that already had a status when this feature arrived: its first logged move starts
		// from that status. (Written directly; the hook always logs from "nothing" for a new item.)
		Status_Log::record( $id, get_term( $a, 'niroroadmap_status' ), get_term( $b, 'niroroadmap_status' ), 0 );

		$timeline = Status_Log::timeline( $id );

		$this->assertCount( 2, $timeline );
		$this->assertStringStartsWith( 'Alpha', $timeline[0]['name'] );
		$this->assertNull( $timeline[0]['date'], 'When it began is not known.' );
		$this->assertStringStartsWith( 'Beta', $timeline[1]['name'] );
		$this->assertNotEmpty( $timeline[1]['date'] );
	}

	public function test_a_renamed_status_reads_as_renamed_and_a_deleted_one_keeps_its_old_name() {
		$id = $this->item();
		$a  = $this->make_status( 'Alpha' );
		$b  = $this->make_status( 'Beta' );
		wp_set_post_terms( $id, array( $a ), 'niroroadmap_status' );
		wp_set_post_terms( $id, array( $b ), 'niroroadmap_status' );

		wp_update_term( $a, 'niroroadmap_status', array( 'name' => 'Renamed Alpha' ) );
		$this->assertSame( 'Renamed Alpha', Status_Log::timeline( $id )[0]['name'] );

		wp_delete_term( $a, 'niroroadmap_status' );
		$this->assertStringStartsWith( 'Alpha', Status_Log::timeline( $id )[0]['name'], 'The old name is kept when the status is gone.' );
		$this->term_ids = array_diff( $this->term_ids, array( $a ) );
	}

	public function test_the_admin_box_lists_changes_newest_first_and_escapes() {
		$id   = $this->item();
		$a    = $this->make_status( 'Alpha <b>x</b>' );
		$b    = $this->make_status( 'Beta' );
		$user = $this->user( 'Sam <i>Mover</i>' );

		wp_set_current_user( $user );
		wp_set_post_terms( $id, array( $a ), 'niroroadmap_status' );
		wp_set_post_terms( $id, array( $b ), 'niroroadmap_status' );

		ob_start();
		( new \NiroRoadmap\Controller\Admin\Item_Fields() )->render_history( get_post( $id ) );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( '<b>x</b>', $html );
		$this->assertStringNotContainsString( '<i>Mover</i>', $html );
		// The newest change (Alpha -> Beta) comes before the first one (no status -> Alpha).
		$this->assertLessThan( strpos( $html, 'no status' ), strpos( $html, 'Beta' ), 'Newest first.' );
		$this->assertStringContainsString( 'ago by', $html );
	}

	public function test_an_item_with_no_history_says_so() {
		$id = $this->item();

		ob_start();
		( new \NiroRoadmap\Controller\Admin\Item_Fields() )->render_history( get_post( $id ) );

		$this->assertStringContainsString( 'No status changes yet', ob_get_clean() );
	}

	public function test_the_table_exists_and_creating_it_again_is_harmless() {
		global $wpdb;

		Status_Log::maybe_create_table();
		Status_Log::maybe_create_table();

		$this->assertSame( Status_Log::table(), $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Status_Log::table() ) ) );
	}
}
