<?php
use NiroRoadmap\Model\Comment;
use PHPUnit\Framework\TestCase;

/**
 * Comments on roadmap items: what is shown, what is accepted, what is never exposed.
 */
class CommentTest extends TestCase {

	private $item_ids = array();

	private $user_ids = array();

	protected function setUp(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_set_current_user( 0 );
		$_SERVER['REMOTE_ADDR']     = '203.0.113.7';
		$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

		// Same-IP flood control and "previously approved" would make these tests order-dependent.
		add_filter( 'wp_is_comment_flood', '__return_false', 99 ); // WordPress adds its own check lazily, so go after it.
		add_filter( 'pre_option_comment_previously_approved', '__return_zero' );
		add_filter( 'niroroadmap_setting_comments_enabled', '__return_true' );
	}

	protected function tearDown(): void {
		foreach ( $this->item_ids as $id ) {
			wp_delete_post( $id, true ); // Deletes its comments too.
		}
		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		wp_set_current_user( 0 );
		remove_filter( 'wp_is_comment_flood', '__return_false', 99 );
		remove_all_filters( 'pre_option_comment_previously_approved' );
		remove_all_filters( 'pre_option_comment_moderation' );
		remove_all_filters( 'pre_option_comment_registration' );
		remove_all_filters( 'niroroadmap_setting_comments_enabled' );
		remove_all_filters( 'niroroadmap_setting_comments_order' );
		remove_all_filters( 'niroroadmap_setting_comments_login' );
		remove_all_filters( 'niroroadmap_setting_comments_auto_close' );
		remove_all_filters( 'niroroadmap_comment_max_length' );
		remove_all_actions( 'niroroadmap_comment_created' );
	}

	private function item( $args = array() ) {
		$id = wp_insert_post(
			$args + array(
				'post_type'      => 'niroroadmap_item',
				'post_status'    => 'publish',
				'post_title'     => 'Comment test ' . wp_generate_password( 6, false ),
				'comment_status' => 'open',
			)
		);

		return $this->item_ids[] = $id;
	}

	private function user( $role ) {
		return $this->user_ids[] = wp_insert_user(
			array(
				'user_login' => 'ctest_' . wp_generate_password( 8, false ),
				'user_pass'  => wp_generate_password(),
				'user_email' => wp_generate_password( 8, false ) . '@example.test',
				'role'       => $role,
				'first_name' => ucfirst( $role ),
			)
		);
	}

	/**
	 * Post a guest comment. Content is unique each time so WordPress's duplicate check stays quiet.
	 */
	private function post( $item, $extra = array() ) {
		return Comment::create(
			get_post( $item ),
			$extra + array(
				'content' => 'A thoughtful comment ' . wp_generate_password( 10, false ),
				'name'    => 'Guest',
				'email'   => 'guest@example.test',
			)
		);
	}

	public function test_comments_are_off_unless_enabled() {
		// The saved setting on this site is irrelevant: check the default the schema ships with.
		$this->assertFalse( \NiroRoadmap\Helper\Settings::default_for( 'comments_enabled' ) );
		$this->assertFalse( \NiroRoadmap\Helper\Settings::default_for( 'comments_login' ) );
		$this->assertFalse( \NiroRoadmap\Helper\Settings::default_for( 'comments_auto_close' ) );
		$this->assertSame( 'oldest', \NiroRoadmap\Helper\Settings::default_for( 'comments_order' ) );
	}

	public function test_a_guest_can_comment_and_the_count_goes_up() {
		$item   = $this->item();
		$result = $this->post( $item );

		$this->assertIsArray( $result );
		$this->assertFalse( $result['pending'] );
		$this->assertSame( 1, $result['count'] );
		$this->assertSame( 'Guest', $result['comment']['author'] );
		$this->assertSame( 1, (int) get_comments_number( $item ) );
	}

	public function test_emails_and_ip_addresses_are_never_in_the_output() {
		$item = $this->item();
		$this->post( $item, array( 'email' => 'secret.person@example.test' ) );

		$page = Comment::page( get_post( $item ), 1, 20 );
		$json = wp_json_encode( $page );

		$this->assertStringNotContainsString( 'secret.person', $json );
		$this->assertStringNotContainsString( '203.0.113.7', $json );
		$this->assertSame( array( 'id', 'parent', 'author', 'avatar', 'content', 'date', 'date_human', 'team', 'pending', 'replies' ), array_keys( $page['comments'][0] ) );
	}

	public function test_content_is_plain_text_links_are_nofollow_ugc() {
		$item   = $this->item();
		$result = $this->post( $item, array( 'content' => '<script>alert(1)</script><b>bold</b> see https://example.com/page <img src=x onerror=alert(2)>' ) );

		$html = $result['comment']['content'];

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'alert(1)', $html );
		$this->assertMatchesRegularExpression( '/<a [^>]*href="https:\/\/example\.com\/page"[^>]*rel="[^"]*nofollow[^"]*ugc[^"]*"/', $html );
	}

	public function test_a_comment_containing_markup_stored_by_an_admin_is_escaped_on_output() {
		$item = $this->item();
		$id   = wp_insert_comment(
			array(
				'comment_post_ID'  => $item,
				'comment_content'  => '<img src=x onerror=alert(1)> hi',
				'comment_approved' => 1,
				'comment_author'   => 'Old',
			)
		);

		$this->assertStringNotContainsString( '<img', Comment::format( get_comment( $id ) )['content'] );
	}

	public function test_empty_and_overlong_comments_are_refused() {
		$item = $this->item();

		$this->assertSame( 'comment_empty', $this->post( $item, array( 'content' => "  <b></b>  \n" ) )->get_error_code() );

		add_filter( 'niroroadmap_comment_max_length', fn() => 10 );
		$this->assertSame( 'comment_too_long', $this->post( $item, array( 'content' => str_repeat( 'x', 11 ) ) )->get_error_code() );
		$this->assertIsArray( $this->post( $item, array( 'content' => str_repeat( 'y', 10 ) ) ) );
	}

	public function test_guests_must_give_name_and_email_when_wordpress_says_so() {
		$item = $this->item();

		$result = $this->post( $item, array( 'name' => '', 'email' => '' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_replies_attach_to_their_parent_one_level_deep() {
		$item   = $this->item();
		$parent = $this->post( $item )['comment']['id'];
		$reply  = $this->post( $item, array( 'parent' => $parent ) );

		$this->assertIsArray( $reply );
		$this->assertSame( $parent, $reply['comment']['parent'] );

		$page = Comment::page( get_post( $item ), 1, 20 );
		$this->assertCount( 1, $page['comments'] );
		$this->assertCount( 1, $page['comments'][0]['replies'] );

		// No reply to a reply, none to a comment of another item, none to a missing one.
		$this->assertSame( 'comment_bad_parent', $this->post( $item, array( 'parent' => $reply['comment']['id'] ) )->get_error_code() );
		$other = $this->item();
		$this->assertSame( 'comment_bad_parent', $this->post( $other, array( 'parent' => $parent ) )->get_error_code() );
		$this->assertSame( 'comment_bad_parent', $this->post( $item, array( 'parent' => 999999999 ) )->get_error_code() );
	}

	public function test_pagination_and_order() {
		$item = $this->item();
		foreach ( array( 'first', 'second', 'third' ) as $n => $word ) {
			$id = $this->post( $item, array( 'content' => "{$word} comment" ) )['comment']['id'];
			wp_update_comment( array( 'comment_ID' => $id, 'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - 1000 + $n * 10 ), 'comment_date' => gmdate( 'Y-m-d H:i:s', time() - 1000 + $n * 10 ) ) );
		}

		$oldest = Comment::page( get_post( $item ), 1, 2 );
		$this->assertSame( 2, $oldest['total_pages'] );
		$this->assertSame( 3, $oldest['count'] );
		$this->assertStringContainsString( 'first', $oldest['comments'][0]['content'] );
		$this->assertStringContainsString( 'third', Comment::page( get_post( $item ), 2, 2 )['comments'][0]['content'] );

		add_filter( 'niroroadmap_setting_comments_order', fn() => 'newest' );
		$this->assertStringContainsString( 'third', Comment::page( get_post( $item ), 1, 2 )['comments'][0]['content'] );
	}

	public function test_held_comments_are_pending_hidden_from_the_public_and_shown_to_their_author() {
		add_filter( 'pre_option_comment_moderation', fn() => '1' ); // A string, like the real option: core compares strictly.

		$item   = $this->item();
		$author = $this->user( 'subscriber' );
		wp_set_current_user( $author );

		$result = Comment::create( get_post( $item ), array( 'content' => 'Please review me' ) );
		$this->assertTrue( $result['pending'] );
		$this->assertSame( 0, $result['count'] );

		// The author sees it, flagged.
		$mine = Comment::page( get_post( $item ), 1, 20 );
		$this->assertCount( 0, $mine['comments'] );
		$this->assertCount( 1, $mine['pending'] );
		$this->assertTrue( $mine['pending'][0]['pending'] );

		// Everyone else doesn't.
		wp_set_current_user( $this->user( 'subscriber' ) );
		$this->assertCount( 0, Comment::page( get_post( $item ), 1, 20 )['pending'] );
		wp_set_current_user( 0 );
		$public = Comment::page( get_post( $item ), 1, 20 );
		$this->assertCount( 0, $public['pending'] );
		$this->assertCount( 0, $public['comments'] );
	}

	public function test_team_members_are_flagged() {
		$item = $this->item();

		wp_set_current_user( $this->user( 'editor' ) );
		$this->assertTrue( Comment::create( get_post( $item ), array( 'content' => 'From the team' ) )['comment']['team'] );

		wp_set_current_user( $this->user( 'subscriber' ) );
		$this->assertFalse( Comment::create( get_post( $item ), array( 'content' => 'From a user' ) )['comment']['team'] );

		wp_set_current_user( 0 );
		$this->assertFalse( $this->post( $item )['comment']['team'] );
	}

	public function test_closed_items_take_no_comments() {
		$item = $this->item( array( 'comment_status' => 'closed' ) );

		$this->assertFalse( Comment::state( get_post( $item ) )['open'] );
		$this->assertSame( 'comments_closed', $this->post( $item )->get_error_code() );
	}

	public function test_login_can_be_required_by_the_setting_or_by_wordpress() {
		$item = $this->item();

		add_filter( 'niroroadmap_setting_comments_login', '__return_true' );
		$this->assertFalse( Comment::state( get_post( $item ) )['can_comment'] );
		$this->assertSame( 'comment_login_required', $this->post( $item )->get_error_code() );

		wp_set_current_user( $this->user( 'subscriber' ) );
		$this->assertTrue( Comment::state( get_post( $item ) )['can_comment'] );
		$this->assertIsArray( Comment::create( get_post( $item ), array( 'content' => 'Logged in now' ) ) );

		remove_all_filters( 'niroroadmap_setting_comments_login' );
		wp_set_current_user( 0 );
		add_filter( 'pre_option_comment_registration', fn() => 1 );
		$this->assertFalse( Comment::state( get_post( $item ) )['can_comment'] );
	}

	public function test_the_created_action_fires_for_other_features_to_use() {
		$item  = $this->item();
		$fired = 0;
		add_action( 'niroroadmap_comment_created', function ( $comment, $post ) use ( &$fired, $item ) {
			$this->assertSame( $item, $post->ID );
			++$fired;
		}, 10, 2 );

		$this->post( $item );

		$this->assertSame( 1, $fired );
	}

	public function test_moving_an_item_to_completed_closes_comments_only_when_the_setting_is_on() {
		$completed = get_term_by( 'slug', 'completed', 'niroroadmap_status' );
		$planned   = get_term_by( 'slug', 'planned', 'niroroadmap_status' );
		$this->assertNotFalse( $completed, 'The default Completed status is needed.' );

		$off = $this->item();
		wp_set_post_terms( $off, array( $completed->term_id ), 'niroroadmap_status' );
		$this->assertSame( 'open', get_post( $off )->comment_status );

		add_filter( 'niroroadmap_setting_comments_auto_close', '__return_true' );

		$on = $this->item();
		wp_set_post_terms( $on, array( $planned->term_id ), 'niroroadmap_status' );
		$this->assertSame( 'open', get_post( $on )->comment_status, 'Other statuses leave comments open.' );

		wp_set_post_terms( $on, array( $completed->term_id ), 'niroroadmap_status' );
		$this->assertSame( 'closed', get_post( $on )->comment_status );

		wp_set_post_terms( $on, array( $planned->term_id ), 'niroroadmap_status' );
		$this->assertSame( 'closed', get_post( $on )->comment_status, 'Moving back does not reopen.' );
	}

	public function test_only_public_items_are_reachable() {
		$draft = $this->item( array( 'post_status' => 'draft' ) );
		$locked = $this->item( array( 'post_password' => 'secret' ) );
		$open  = $this->item();

		$this->assertNull( \NiroRoadmap\Model\Roadmap::get_public_item( $draft ) );
		$this->assertNull( \NiroRoadmap\Model\Roadmap::get_public_item( $locked ) );
		$this->assertNull( \NiroRoadmap\Model\Roadmap::get_public_item( 1 ), 'A non-item post is not an item.' );
		$this->assertNotNull( \NiroRoadmap\Model\Roadmap::get_public_item( $open ) );
	}
}
