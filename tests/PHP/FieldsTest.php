<?php
use NiroRoadmap\Controller\Admin\Item_Fields;
use NiroRoadmap\Model\Fields;
use NiroRoadmap\Model\Roadmap;
use PHPUnit\Framework\TestCase;

/**
 * Item fields: validation, what is public, what is never public, the editor box, the board.
 */
class FieldsTest extends TestCase {

	private $item_ids = array();

	private $user_ids = array();

	private $term_ids = array();

	private $att_ids = array();

	private $overrides = array();

	protected function setUp(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		wp_set_current_user( 0 );
		unset( $GLOBALS['wp_actions']['niroroadmap_modal_rendered'], $GLOBALS['wp_actions']['niroroadmap_suggest_rendered'] );

		// The site's saved settings must not decide these tests.
		foreach ( array(
			'show_target'      => false,
			'show_history'     => false,
			'show_vote_counts' => true,
			'toolbar_enabled'  => false,
			'default_product'  => 0,
		) as $key => $value ) {
			$this->set( $key, $value );
		}
	}

	protected function tearDown(): void {
		foreach ( $this->item_ids as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $this->att_ids as $id ) {
			wp_delete_attachment( $id, true );
		}
		foreach ( $this->user_ids as $id ) {
			wp_delete_user( $id );
		}
		foreach ( $this->term_ids as $term ) {
			wp_delete_term( $term[0], $term[1] );
		}
		foreach ( $this->overrides as $hook => $callback ) {
			remove_filter( $hook, $callback );
		}
		$_POST = array();
		delete_transient( 'niroroadmap_field_errors_' . get_current_user_id() );
		wp_set_current_user( 0 );
	}

	private function set( $key, $value ) {
		$hook = "niroroadmap_setting_{$key}";

		if ( isset( $this->overrides[ $hook ] ) ) {
			remove_filter( $hook, $this->overrides[ $hook ] );
		}

		$this->overrides[ $hook ] = static function () use ( $value ) {
			return $value;
		};
		add_filter( $hook, $this->overrides[ $hook ] );
	}

	private function item( $title = null, $status = 'under-review' ) {
		$id = wp_insert_post( array( 'post_type' => 'niroroadmap_item', 'post_status' => 'publish', 'post_title' => $title ?? 'Fields item ' . wp_generate_password( 6, false ) ) );
		$this->item_ids[] = $id;
		$status && wp_set_object_terms( $id, array( (int) get_term_by( 'slug', $status, 'niroroadmap_status' )->term_id ), 'niroroadmap_status' );

		return $id;
	}

	private function user( $role ) {
		return $this->user_ids[] = wp_insert_user( array( 'user_login' => 'ftest_' . wp_generate_password( 8, false ), 'user_pass' => wp_generate_password(), 'user_email' => wp_generate_password( 8, false ) . '@example.test', 'role' => $role ) );
	}

	// --- Targets ---------------------------------------------------------------------------------

	public function test_a_target_is_a_date_or_a_quarter() {
		$this->assertSame( array( 'text' => '2026-12-31', 'sort' => '2026-12-31' ), Fields::parse_target( '2026-12-31' ) );
		$this->assertSame( array( 'text' => 'Q4 2026', 'sort' => '2026-10-01' ), Fields::parse_target( 'Q4 2026' ) );
		$this->assertSame( array( 'text' => 'Q1 2027', 'sort' => '2027-01-01' ), Fields::parse_target( ' q1-2027 ' ) );
		$this->assertSame( array( 'text' => 'Q2 2027', 'sort' => '2027-04-01' ), Fields::parse_target( 'Q22027' ) );
		$this->assertSame( array( 'text' => 'Q3 2026', 'sort' => '2026-07-01' ), Fields::parse_target( 'Q3 2026' ) );
		$this->assertSame( array( 'text' => '', 'sort' => '' ), Fields::parse_target( '   ' ), 'Empty clears it.' );

		foreach ( array( 'soon', 'Q5 2026', 'Q0 2026', 'Q1 1999', 'Q1 2101', '2026-13-01', '2026-02-30', '31/12/2026', '2026-1-1', '<b>Q1 2026</b>' ) as $bad ) {
			$this->assertNull( Fields::parse_target( $bad ), "'{$bad}' is neither a date nor a quarter." );
		}
	}

	public function test_quarters_sort_by_their_first_day_among_dates() {
		$order = array( Fields::parse_target( 'Q4 2026' )['sort'], Fields::parse_target( '2026-11-15' )['sort'], Fields::parse_target( 'Q1 2027' )['sort'], Fields::parse_target( '2026-09-30' )['sort'] );
		sort( $order );

		$this->assertSame( array( '2026-09-30', '2026-10-01', '2026-11-15', '2027-01-01' ), $order );
	}

	// --- Validation ------------------------------------------------------------------------------

	public function test_good_input_is_accepted_and_stored_cleanly() {
		$result = Fields::sanitize(
			array(
				'target'     => 'Q4 2026',
				'release'    => '2026-11-02',
				'version'    => ' 2.5.0 ',
				'effort'     => 'M',
				'notes'      => "Needs <b>design</b> first\nthen build",
				'link'       => 'https://example.com/pull/12',
				'hide_votes' => '1',
				'pinned'     => '0',
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( 'Q4 2026', $result['values'][ Fields::TARGET ] );
		$this->assertSame( '2026-10-01', $result['values'][ Fields::TARGET_SORT ] );
		$this->assertSame( '2026-11-02', $result['values'][ Fields::RELEASE ] );
		$this->assertSame( '2.5.0', $result['values'][ Fields::VERSION ] );
		$this->assertSame( 'M', $result['values'][ Fields::EFFORT ] );
		$this->assertSame( "Needs design first\nthen build", $result['values'][ Fields::NOTES ], 'Tags go, line breaks stay.' );
		$this->assertSame( 'https://example.com/pull/12', $result['values'][ Fields::LINK ] );
		$this->assertSame( '1', $result['values'][ Fields::HIDE_VOTES ] );
		$this->assertSame( '', $result['values'][ Fields::PINNED ], 'Off means remove.' );
	}

	public function test_each_bad_value_is_refused_with_a_message_and_left_out() {
		$result = Fields::sanitize(
			array(
				'target'  => 'whenever',
				'release' => '2026-02-31',
				'version' => str_repeat( 'v', Fields::MAX_VERSION + 1 ),
				'effort'  => 'XXL',
				'notes'   => str_repeat( 'n', Fields::MAX_NOTES + 1 ),
				'link'    => 'javascript:alert(1)',
			)
		);

		$this->assertCount( 6, $result['errors'] );
		$this->assertSame( array(), $result['values'], 'Nothing invalid is kept, so saved values survive.' );
	}

	public function test_only_web_links_are_allowed() {
		foreach ( array( 'javascript:alert(1)', 'data:text/html,x', 'ftp://example.com/x', 'file:///etc/passwd', 'not a url', 'example.com/page', '//example.com/x', 'http://', 'https:// spaced.example.com' ) as $bad ) {
			$this->assertCount( 1, Fields::sanitize( array( 'link' => $bad ) )['errors'], "'{$bad}' must be refused." );
		}

		$this->assertSame( 'http://example.com/a', Fields::sanitize( array( 'link' => 'http://example.com/a' ) )['values'][ Fields::LINK ] );
		$this->assertSame( '', Fields::sanitize( array( 'link' => '' ) )['values'][ Fields::LINK ], 'Empty clears it.' );
	}

	public function test_a_value_posted_with_slashes_is_unslashed_once() {
		$result = Fields::sanitize( wp_slash( array( 'notes' => "It's \"quoted\"", 'version' => "O'Brien" ) ) );

		$this->assertSame( "It's \"quoted\"", $result['values'][ Fields::NOTES ] );
		$this->assertSame( "O'Brien", $result['values'][ Fields::VERSION ] );
	}

	public function test_saving_empty_values_removes_the_meta() {
		$id = $this->item();
		Fields::save( $id, array( Fields::NOTES => 'something', Fields::PINNED => '1' ) );
		$this->assertSame( 'something', get_post_meta( $id, Fields::NOTES, true ) );

		Fields::save( $id, array( Fields::NOTES => '', Fields::PINNED => '' ) );

		$this->assertFalse( metadata_exists( 'post', $id, Fields::NOTES ) );
		$this->assertFalse( metadata_exists( 'post', $id, Fields::PINNED ) );
	}

	public function test_core_validates_writes_that_bypass_the_form() {
		$id = $this->item();

		update_post_meta( $id, Fields::EFFORT, 'HUGE' );
		update_post_meta( $id, Fields::LINK, 'javascript:alert(1)' );
		update_post_meta( $id, Fields::TARGET, 'whenever' );
		update_post_meta( $id, Fields::HIDE_VOTES, 'yes please' );

		$this->assertSame( '', get_post_meta( $id, Fields::EFFORT, true ) );
		$this->assertSame( '', get_post_meta( $id, Fields::LINK, true ) );
		$this->assertSame( '', get_post_meta( $id, Fields::TARGET, true ) );
		$this->assertSame( '', get_post_meta( $id, Fields::HIDE_VOTES, true ) );
	}

	// --- What is public --------------------------------------------------------------------------

	public function test_internal_fields_are_never_in_the_public_facts() {
		$id = $this->item();
		Fields::save( $id, array( Fields::EFFORT => 'XL', Fields::NOTES => 'SECRET-NOTE-TEXT', Fields::TARGET => 'Q1 2027', Fields::TARGET_SORT => '2027-01-01', Fields::LINK => 'https://example.com/x', Fields::VERSION => '3.0', Fields::RELEASE => '2026-10-02' ) );

		$this->set( 'show_target', true );
		$json = wp_json_encode( Fields::public_facts( get_post( $id ) ) );

		$this->assertStringNotContainsString( 'SECRET-NOTE-TEXT', $json );
		$this->assertStringNotContainsString( 'XL', $json );
		$this->assertStringContainsString( 'Q1 2027', $json );
	}

	public function test_the_target_is_public_only_when_the_setting_says_so() {
		$id = $this->item();
		Fields::save( $id, array( Fields::TARGET => '2026-12-31' ) );

		$this->assertArrayNotHasKey( 'target', Fields::public_facts( get_post( $id ) ) );

		$this->set( 'show_target', true );
		$this->assertSame( wp_date( get_option( 'date_format' ), strtotime( '2026-12-31 12:00:00 UTC' ), new DateTimeZone( 'UTC' ) ), Fields::public_facts( get_post( $id ) )['target'] );
	}

	public function test_release_version_and_link_are_public_when_filled_in() {
		$id = $this->item();
		$this->assertSame( array(), Fields::public_facts( get_post( $id ) ), 'A bare item has no facts.' );

		Fields::save( $id, array( Fields::RELEASE => '2026-10-02', Fields::VERSION => '2.0', Fields::LINK => 'https://example.com/docs' ) );
		$facts = Fields::public_facts( get_post( $id ) );

		$this->assertSame( '2.0', $facts['version'] );
		$this->assertSame( 'https://example.com/docs', $facts['link'] );
		$this->assertArrayHasKey( 'release', $facts );
	}

	public function test_core_rest_does_not_expose_any_of_the_fields() {
		$id = $this->item();
		Fields::save( $id, array( Fields::NOTES => 'SECRET-NOTE-TEXT', Fields::EFFORT => 'XL', Fields::TARGET => 'Q1 2027', Fields::LINK => 'https://example.com/x', Fields::PINNED => '1' ) );

		$public = wp_json_encode( rest_do_request( new WP_REST_Request( 'GET', "/wp/v2/niroroadmap_item/{$id}" ) )->get_data() );
		$this->assertStringNotContainsString( 'SECRET-NOTE-TEXT', $public );
		$this->assertStringNotContainsString( '_niroroadmap_', $public );

		wp_set_current_user( $this->user( 'administrator' ) );
		$request = new WP_REST_Request( 'GET', "/wp/v2/niroroadmap_item/{$id}" );
		$request->set_param( 'context', 'edit' );
		$edit = wp_json_encode( rest_do_request( $request )->get_data() );
		$this->assertStringNotContainsString( 'SECRET-NOTE-TEXT', $edit, 'Not even in the editor view of core REST.' );
	}

	public function test_the_cover_image_is_the_featured_image() {
		$id   = $this->item();
		$file = wp_upload_bits( 'niroroadmap-test-' . wp_generate_password( 6, false ) . '.png', null, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ) );
		$this->assertEmpty( $file['error'] );

		$att = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'cover', 'post_status' => 'inherit' ), $file['file'], $id );
		$this->att_ids[] = $att;
		wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $file['file'] ) );
		update_post_meta( $att, '_wp_attachment_image_alt', 'A cover' );
		set_post_thumbnail( $id, $att );

		$facts = Fields::public_facts( get_post( $id ) );

		$this->assertMatchesRegularExpression( '#^https?://#', $facts['cover']['url'] );
		$this->assertSame( 'A cover', $facts['cover']['alt'] );
		$this->assertTrue( post_type_supports( 'niroroadmap_item', 'thumbnail' ) );
	}

	// --- The board -------------------------------------------------------------------------------

	public function test_pinned_items_lead_their_column_and_keep_manual_order_otherwise() {
		$a = $this->item( 'Pin test A' );
		$b = $this->item( 'Pin test B' );
		$c = $this->item( 'Pin test C' );
		foreach ( array( $a => 1, $b => 2, $c => 3 ) as $id => $order ) {
			wp_update_post( array( 'ID' => $id, 'menu_order' => -5000 + $order ) );
		}
		Fields::save( $c, array( Fields::PINNED => '1' ) );

		$html = Roadmap::get_roadmap();

		$pos = static function ( $id ) use ( $html ) {
			return strpos( $html, 'id="nr-task-' . $id . '"' );
		};
		$this->assertLessThan( $pos( $a ), $pos( $c ), 'The pinned item is first.' );
		$this->assertLessThan( $pos( $b ), $pos( $a ), 'The rest keep the manual order.' );
		$this->assertStringContainsString( 'data-pinned="1"', $html );
		$this->assertStringContainsString( 'nr-task-pin', $html );
	}

	public function test_an_item_can_hide_its_own_vote_counts() {
		$shown  = $this->item( 'Votes shown' );
		$hidden = $this->item( 'Votes hidden' );
		update_post_meta( $shown, 'upvote', 7 );
		update_post_meta( $hidden, 'upvote', 9 );
		Fields::save( $hidden, array( Fields::HIDE_VOTES => '1' ) );

		$html = Roadmap::get_roadmap();

		$card = static function ( $id ) use ( $html ) {
			$start = strpos( $html, 'id="nr-task-' . $id . '"' );

			return substr( $html, $start, (int) strpos( $html, '</article>', $start ) - $start );
		};
		$this->assertStringContainsString( 'nr-task-votes-count', $card( $shown ) );
		$this->assertStringNotContainsString( 'nr-task-votes-count', $card( $hidden ) );
		$this->assertStringNotContainsString( '>9<', $card( $hidden ) );
	}

	public function test_an_item_with_every_field_empty_works() {
		$id = $this->item( 'Bare item' );

		$this->assertStringContainsString( 'Bare item', Roadmap::get_roadmap() );
		$this->assertFalse( Fields::is_pinned( $id ) );
		$this->assertFalse( Fields::votes_hidden( $id ) );
		$this->assertSame( array(), Fields::public_facts( get_post( $id ) ) );
	}

	// --- The editor ------------------------------------------------------------------------------

	private function post_form( $values, $nonce = true ) {
		$_POST = array( 'niroroadmap_fields' => wp_slash( $values ) );

		if ( $nonce ) {
			$_POST[ Item_Fields::NONCE . '_nonce' ] = wp_create_nonce( Item_Fields::NONCE );
		}
	}

	public function test_the_editor_box_saves_with_a_nonce_and_permission() {
		$id    = $this->item();
		$admin = new Item_Fields();

		wp_set_current_user( $this->user( 'administrator' ) );
		$this->post_form( array( 'target' => 'Q2 2027', 'effort' => 'S', 'notes' => 'hello', 'pinned' => '1' ) );
		$admin->save( $id, get_post( $id ) );

		$this->assertSame( 'Q2 2027', get_post_meta( $id, Fields::TARGET, true ) );
		$this->assertSame( '2027-04-01', get_post_meta( $id, Fields::TARGET_SORT, true ) );
		$this->assertSame( 'S', get_post_meta( $id, Fields::EFFORT, true ) );
		$this->assertSame( '1', get_post_meta( $id, Fields::PINNED, true ) );
		$this->assertFalse( metadata_exists( 'post', $id, Fields::HIDE_VOTES ), 'An unticked box is off.' );
	}

	public function test_the_editor_box_ignores_a_missing_nonce_a_missing_permission_and_revisions() {
		$id    = $this->item();
		$admin = new Item_Fields();

		wp_set_current_user( $this->user( 'administrator' ) );
		$this->post_form( array( 'notes' => 'no nonce' ), false );
		$admin->save( $id, get_post( $id ) );
		$this->assertFalse( metadata_exists( 'post', $id, Fields::NOTES ), 'No nonce, no save.' );

		wp_set_current_user( $this->user( 'subscriber' ) );
		$this->post_form( array( 'notes' => 'no permission' ) );
		$admin->save( $id, get_post( $id ) );
		$this->assertFalse( metadata_exists( 'post', $id, Fields::NOTES ), 'A subscriber can not edit items.' );

		wp_set_current_user( $this->user( 'administrator' ) );
		$revision = wp_save_post_revision( $id ) ?: $id;
		$this->post_form( array( 'notes' => 'on a revision' ) );
		if ( $revision !== $id ) {
			$admin->save( $revision, get_post( $revision ) );
			$this->assertFalse( metadata_exists( 'post', $revision, Fields::NOTES ) );
		}
	}

	public function test_quick_edit_and_other_saves_without_the_box_leave_the_fields_alone() {
		$id    = $this->item();
		$admin = new Item_Fields();
		Fields::save( $id, array( Fields::NOTES => 'keep me', Fields::PINNED => '1', Fields::TARGET => 'Q1 2027', Fields::TARGET_SORT => '2027-01-01' ) );

		// Quick edit, bulk edit, REST and code all fire save_post without the box's nonce or fields.
		wp_set_current_user( $this->user( 'administrator' ) );
		$_POST = array( 'post_title' => 'Renamed in quick edit' );
		$admin->save( $id, get_post( $id ) );
		wp_update_post( array( 'ID' => $id, 'post_title' => 'Renamed again' ) );

		$this->assertSame( 'keep me', get_post_meta( $id, Fields::NOTES, true ) );
		$this->assertSame( '1', get_post_meta( $id, Fields::PINNED, true ) );
		$this->assertSame( 'Q1 2027', get_post_meta( $id, Fields::TARGET, true ) );
	}

	public function test_sorting_the_list_keeps_a_meta_query_another_filter_set() {
		$tagged = $this->item( 'Meta tagged' );
		$other  = $this->item( 'Meta other' );
		update_post_meta( $tagged, 'upvote', 3 );
		update_post_meta( $other, 'upvote', 8 );
		update_post_meta( $tagged, 'other_plugin_flag', 'yes' );

		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		set_current_screen( 'edit-niroroadmap_item' );
		$query = new WP_Query( array( 'post_type' => 'niroroadmap_item', 'post__in' => array( $tagged, $other ), 'post_status' => 'any', 'orderby' => 'niroroadmap_votes', 'order' => 'DESC', 'fields' => 'ids', 'no_found_rows' => true, 'meta_query' => array( array( 'key' => 'other_plugin_flag', 'value' => 'yes' ) ) ) );
		$GLOBALS['wp_the_query'] = $query;
		( new Item_Fields() )->sort_list( $query );

		$this->assertSame( array( $tagged ), $query->get_posts(), 'The other filter still applies.' );
		set_current_screen( 'front' );
	}

	public function test_a_bad_value_in_the_box_keeps_the_old_one_and_tells_the_editor() {
		$id    = $this->item();
		$admin = new Item_Fields();
		Fields::save( $id, array( Fields::TARGET => 'Q1 2027', Fields::TARGET_SORT => '2027-01-01' ) );

		$user = $this->user( 'administrator' );
		wp_set_current_user( $user );
		$this->post_form( array( 'target' => 'next month', 'notes' => 'kept anyway' ) );
		$admin->save( $id, get_post( $id ) );

		$this->assertSame( 'Q1 2027', get_post_meta( $id, Fields::TARGET, true ), 'The old target stays.' );
		$this->assertSame( 'kept anyway', get_post_meta( $id, Fields::NOTES, true ), 'Valid fields in the same form still save.' );

		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';

		// Not on some other admin page: the message waits for the item's own screen.
		set_current_screen( 'dashboard' );
		ob_start();
		$admin->show_errors();
		$this->assertSame( '', ob_get_clean(), 'Nothing on the dashboard.' );
		$this->assertNotEmpty( get_transient( 'niroroadmap_field_errors_' . $user ), 'And it is still waiting.' );

		// The classic editor shows it as a normal notice, once.
		set_current_screen( 'niroroadmap_item' );
		get_current_screen()->is_block_editor( false );
		ob_start();
		$admin->show_errors();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'target must be a date', $html );
		$this->assertFalse( get_transient( 'niroroadmap_field_errors_' . $user ), 'Used up.' );
		set_current_screen( 'front' );
	}

	public function test_the_block_editor_gets_the_message_through_its_own_notices() {
		$id    = $this->item();
		$admin = new Item_Fields();
		$user  = $this->user( 'administrator' );
		wp_set_current_user( $user );

		set_transient( 'niroroadmap_field_errors_' . $user, array( 'Plain <b>message</b>' ), 300 );

		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		set_current_screen( 'niroroadmap_item' );
		get_current_screen()->is_block_editor( true );

		// The classic notice stays out of the way, so it can't use the message up.
		ob_start();
		$admin->show_errors();
		$this->assertSame( '', ob_get_clean() );
		$this->assertNotEmpty( get_transient( 'niroroadmap_field_errors_' . $user ) );

		$admin->editor_notices();

		$script = wp_scripts()->get_data( 'wp-edit-post', 'after' );
		$this->assertStringContainsString( 'core/notices', implode( ' ', (array) $script ) );
		$this->assertStringContainsString( 'Plain \u003Cb\u003Emessage', implode( ' ', (array) $script ), 'The message goes in as data, escaped.' );
		$this->assertFalse( get_transient( 'niroroadmap_field_errors_' . $user ) );
		set_current_screen( 'front' );
	}

	public function test_the_box_escapes_what_it_prints() {
		$id = $this->item();
		update_post_meta( $id, Fields::NOTES, '</textarea><script>alert(1)</script>' );
		// A value stored by other code, bypassing the form's checks, must still print safely.
		global $wpdb;
		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => '"><script>alert(2)</script>' ), array( 'post_id' => $id, 'meta_key' => Fields::NOTES ) );
		wp_cache_delete( $id, 'post_meta' );

		wp_set_current_user( $this->user( 'administrator' ) );
		ob_start();
		( new Item_Fields() )->render_fields( get_post( $id ) );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( '<script>alert(2)', $html );
		$this->assertStringContainsString( 'name="niroroadmap_fields[target]"', $html );
	}

	public function test_the_votes_list_can_be_sorted_and_keeps_items_without_votes() {
		$none = $this->item( 'Sort none' );
		$low  = $this->item( 'Sort low' );
		$high = $this->item( 'Sort high' );
		update_post_meta( $low, 'upvote', 5 );
		update_post_meta( $high, 'upvote', 10 );

		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		set_current_screen( 'edit-niroroadmap_item' );
		$query = new WP_Query( array( 'post_type' => 'niroroadmap_item', 'post__in' => array( $none, $low, $high ), 'post_status' => 'any', 'orderby' => 'niroroadmap_votes', 'order' => 'DESC', 'fields' => 'ids', 'no_found_rows' => true ) );
		$GLOBALS['wp_the_query'] = $query;
		( new Item_Fields() )->sort_list( $query );

		$this->assertSame( array( $high, $low, $none ), $query->get_posts(), 'Most votes first, and the item with no votes is still listed.' );
		set_current_screen( 'front' );
	}
}
