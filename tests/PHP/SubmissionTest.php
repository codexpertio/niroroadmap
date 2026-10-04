<?php
use NiroRoadmap\Helper\Settings;
use NiroRoadmap\Model\Roadmap;
use NiroRoadmap\Model\Submission;
use NiroRoadmap\Model\Vote;
use PHPUnit\Framework\TestCase;

/**
 * Ideas suggested by visitors: stored as pending, validated, private, never public.
 */
class SubmissionTest extends TestCase {

	private $item_ids = array();

	private $user_ids = array();

	private $term_ids = array();

	private $mails = array();

	private $overrides = array();

	protected function setUp(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_set_current_user( 0 );
		unset( $_COOKIE[ \NiroRoadmap\Helper\Voter::COOKIE ] );
		$_SERVER['REMOTE_ADDR']     = '203.0.113.7';
		$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
		Vote::maybe_create_table();

		// Never send real mail; keep what would have been sent.
		add_filter(
			'pre_wp_mail',
			function ( $short, $atts ) {
				$this->mails[] = $atts;

				return true;
			},
			10,
			2
		);

		// The site's saved settings are irrelevant: pin the ones these tests care about.
		$this->set( 'submissions_enabled', false );
		$this->set( 'submissions_login', false );
		$this->set( 'submissions_identity', 'optional' );
		$this->set( 'submissions_notify', true );
		$this->set( 'submissions_email', '' );
		$this->set( 'submissions_auto_vote', false );
		$this->set( 'submissions_status', 0 );
		$this->set( 'vote_who', 'everyone' );
	}

	protected function tearDown(): void {
		foreach ( $this->item_ids as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		foreach ( $this->term_ids as $term ) {
			wp_delete_term( $term[0], $term[1] );
		}
		foreach ( $this->overrides as $hook => $callback ) {
			remove_filter( $hook, $callback );
		}
		remove_all_filters( 'pre_wp_mail' );
		remove_all_actions( 'niroroadmap_submission_created' );
		remove_all_filters( 'niroroadmap_submission_notify_to' );
		wp_set_current_user( 0 );
	}

	/**
	 * Pin a setting for the duration of a test.
	 */
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

	private function submit( $input = array() ) {
		$result = Submission::create(
			$input + array(
				'title'       => 'Dark mode ' . wp_generate_password( 6, false ),
				'description' => 'It would be easier on the eyes.',
			)
		);

		if ( is_array( $result ) ) {
			$this->item_ids[] = $result['id'];
		}

		return $result;
	}

	private function term( $taxonomy, $name ) {
		$term = wp_insert_term( $name . ' ' . wp_generate_password( 5, false ), $taxonomy );
		$this->term_ids[] = array( $term['term_id'], $taxonomy );

		return $term['term_id'];
	}

	private function user( $role = 'subscriber' ) {
		return $this->user_ids[] = wp_insert_user(
			array(
				'user_login'   => 'stest_' . wp_generate_password( 8, false ),
				'user_pass'    => wp_generate_password(),
				'user_email'   => 'user.' . wp_generate_password( 6, false ) . '@example.test',
				'role'         => $role,
				'display_name' => 'Sam Subscriber',
			)
		);
	}

	public function test_a_submission_is_stored_as_pending_and_never_public() {
		$title  = 'Pending idea ' . wp_generate_password( 8, false );
		$result = $this->submit( array( 'title' => $title ) );

		$this->assertIsArray( $result );

		$item = get_post( $result['id'] );
		$this->assertSame( 'pending', $item->post_status );
		$this->assertSame( 'niroroadmap_item', $item->post_type );

		$this->assertNull( Roadmap::get_public_item( $item->ID ), 'Not reachable through the public API checks.' );
		$this->assertStringNotContainsString( $title, Roadmap::get_roadmap(), 'Not on the public board.' );
	}

	public function test_core_rest_api_does_not_expose_pending_items_or_the_submitter() {
		$this->set( 'submissions_identity', 'required' );
		$result = $this->submit( array( 'name' => 'Nina Private', 'email' => 'nina.private@example.test' ) );
		$id     = $result['id'];

		$request  = new WP_REST_Request( 'GET', '/wp/v2/niroroadmap_item' );
		$response = rest_do_request( $request );
		$this->assertNotContains( $id, wp_list_pluck( $response->get_data(), 'id' ) );

		$single = rest_do_request( new WP_REST_Request( 'GET', "/wp/v2/niroroadmap_item/{$id}" ) );
		$this->assertGreaterThanOrEqual( 400, $single->get_status() );

		// Even an editor looking at the raw item doesn't get the private meta from core's REST.
		wp_set_current_user( $this->user( 'administrator' ) );
		$edit = new WP_REST_Request( 'GET', "/wp/v2/niroroadmap_item/{$id}" );
		$edit->set_param( 'context', 'edit' );
		$json = wp_json_encode( rest_do_request( $edit )->get_data() );
		$this->assertStringNotContainsString( 'nina.private', $json );
		$this->assertStringNotContainsString( 'Nina Private', $json );
	}

	public function test_submitter_details_are_kept_in_private_meta() {
		$this->set( 'submissions_identity', 'required' );
		$id = $this->submit( array( 'name' => 'Nina', 'email' => 'nina@example.test' ) )['id'];

		$this->assertSame( 'Nina', get_post_meta( $id, Submission::META_NAME, true ) );
		$this->assertSame( 'nina@example.test', get_post_meta( $id, Submission::META_EMAIL, true ) );
		$this->assertTrue( is_protected_meta( Submission::META_EMAIL, 'post' ), 'Hidden from the custom fields box.' );
	}

	public function test_title_and_description_are_plain_text() {
		$id = $this->submit(
			array(
				'title'       => "  <b>Bold</b> <script>alert(1)</script>title\nacross lines ",
				'description' => "<img src=x onerror=alert(1)>Line one\nLine two <a href='javascript:x'>link</a>",
			)
		)['id'];

		$item = get_post( $id );

		$this->assertStringNotContainsString( '<', $item->post_title );
		$this->assertStringNotContainsString( "\n", $item->post_title );
		$this->assertStringContainsString( 'Bold', $item->post_title );
		$this->assertStringNotContainsString( '<', $item->post_content );
		$this->assertStringNotContainsString( 'onerror', $item->post_content );
		$this->assertStringContainsString( "Line one\nLine two", $item->post_content, 'Line breaks are kept.' );
	}

	public function test_title_and_description_limits() {
		$this->assertSame( 'submission_title', $this->submit( array( 'title' => 'ab' ) )->get_error_code() );
		$this->assertSame( 'submission_title', $this->submit( array( 'title' => '  <b></b> ' ) )->get_error_code() );
		$this->assertSame( 'submission_title_long', $this->submit( array( 'title' => str_repeat( 'x', Submission::max_title() + 1 ) ) )->get_error_code() );
		$this->assertSame( 'submission_description_long', $this->submit( array( 'description' => str_repeat( 'y', Submission::max_description() + 1 ) ) )->get_error_code() );
		$this->assertIsArray( $this->submit( array( 'description' => '' ) ), 'The description is optional.' );
	}

	public function test_name_and_email_follow_the_setting() {
		$this->set( 'submissions_identity', 'required' );
		$this->assertSame( 'submission_identity', $this->submit( array( 'name' => 'Only name' ) )->get_error_code() );
		$this->assertSame( 'submission_identity', $this->submit( array( 'email' => 'only@example.test' ) )->get_error_code() );
		$this->assertSame( 'submission_email', $this->submit( array( 'name' => 'N', 'email' => 'not-an-email' ) )->get_error_code() );
		$this->assertIsArray( $this->submit( array( 'name' => 'N', 'email' => 'n@example.test' ) ) );

		$this->set( 'submissions_identity', 'optional' );
		$this->assertIsArray( $this->submit() );
		$this->assertSame( 'submission_email', $this->submit( array( 'email' => 'broken@' ) )->get_error_code() );

		$this->set( 'submissions_identity', 'off' );
		$id = $this->submit( array( 'name' => 'Ignored', 'email' => 'ignored@example.test' ) )['id'];
		$this->assertSame( '', get_post_meta( $id, Submission::META_EMAIL, true ), 'Not asked, not kept.' );
	}

	public function test_logged_in_users_are_not_asked_and_own_the_item() {
		$this->set( 'submissions_identity', 'required' );
		$user = $this->user();
		wp_set_current_user( $user );

		$id = $this->submit( array( 'name' => 'Spoofed', 'email' => 'spoofed@example.test' ) )['id'];

		$this->assertSame( $user, (int) get_post( $id )->post_author );
		$this->assertSame( 'Sam Subscriber', get_post_meta( $id, Submission::META_NAME, true ), 'The account wins over what the form sent.' );
		$this->assertStringStartsWith( 'user.', get_post_meta( $id, Submission::META_EMAIL, true ) );
	}

	public function test_status_and_product_are_assigned() {
		$status  = $this->term( 'niroroadmap_status', 'Triage' );
		$product = $this->term( 'niroroadmap_product', 'Widgets' );
		$this->set( 'submissions_status', $status );

		$id = $this->submit( array( 'product' => $product ) )['id'];

		$this->assertSame( array( $status ), wp_get_object_terms( $id, 'niroroadmap_status', array( 'fields' => 'ids' ) ) );
		$this->assertSame( array( $product ), wp_get_object_terms( $id, 'niroroadmap_product', array( 'fields' => 'ids' ) ) );

		$this->assertSame( 'submission_product', $this->submit( array( 'product' => 999999999 ) )->get_error_code() );

		$this->set( 'submissions_status', 0 );
		$none = $this->submit()['id'];
		$this->assertSame( array(), wp_get_object_terms( $none, 'niroroadmap_status', array( 'fields' => 'ids' ) ), 'Unassigned when no status is chosen.' );
	}

	public function test_the_submitters_upvote_is_counted_once_when_enabled() {
		$this->set( 'submissions_auto_vote', true );

		$id = $this->submit()['id'];

		$this->assertSame( 1, Vote::counts( $id )['upvote'] );

		// They can't vote on it again.
		$voter = \NiroRoadmap\Helper\Voter::identify();
		$this->assertSame( 'duplicate', Vote::cast( $id, 'upvote', $voter, false )['status'] );
	}

	public function test_no_auto_vote_when_off_or_when_voting_needs_a_login() {
		$this->assertSame( 0, Vote::counts( $this->submit()['id'] )['upvote'] );

		$this->set( 'submissions_auto_vote', true );
		$this->set( 'vote_who', 'logged_in' );
		$this->assertSame( 0, Vote::counts( $this->submit()['id'] )['upvote'], 'A guest could not vote, so no vote is made for them.' );

		wp_set_current_user( $this->user() );
		$this->assertSame( 1, Vote::counts( $this->submit()['id'] )['upvote'] );
	}

	public function test_the_created_action_fires_with_the_item_id() {
		$seen = array();
		add_action( 'niroroadmap_submission_created', function ( $id ) use ( &$seen ) {
			$seen[] = $id;
		} );

		$id = $this->submit()['id'];

		$this->assertSame( array( $id ), $seen );
	}

	public function test_admin_is_emailed_with_who_sent_it_and_where_to_review() {
		$this->set( 'submissions_identity', 'required' );
		$id = $this->submit( array( 'title' => 'Email me this idea', 'name' => 'Nina', 'email' => 'nina@example.test' ) )['id'];

		$this->assertCount( 1, $this->mails );
		$mail = $this->mails[0];

		$this->assertSame( get_option( 'admin_email' ), $mail['to'] );
		$this->assertStringContainsString( 'Email me this idea', $mail['subject'] );
		$this->assertStringContainsString( 'nina@example.test', $mail['message'] );
		$this->assertStringContainsString( (string) $id, $mail['message'], 'Contains the edit link.' );
	}

	public function test_notification_recipient_can_be_set_and_turned_off() {
		$this->set( 'submissions_email', 'inbox@example.test' );
		$this->submit();
		$this->assertSame( 'inbox@example.test', $this->mails[0]['to'] );

		$this->set( 'submissions_email', 'not-valid' );
		$this->submit();
		$this->assertSame( get_option( 'admin_email' ), $this->mails[1]['to'], 'A bad address falls back to the admin email.' );

		$this->set( 'submissions_notify', false );
		$this->submit();
		$this->assertCount( 2, $this->mails );
	}

	public function test_a_mail_header_cannot_be_injected_through_the_title() {
		$this->submit( array( 'title' => "Nice idea\r\nBcc: attacker@example.test" ) );

		$this->assertStringNotContainsString( "\n", $this->mails[0]['subject'] );
		$this->assertStringNotContainsString( "\r", $this->mails[0]['subject'] );
	}

	public function test_similar_ideas_are_only_published_ones() {
		$needle  = 'zebrafish' . wp_generate_password( 6, false, false );
		$public  = wp_insert_post( array( 'post_type' => 'niroroadmap_item', 'post_status' => 'publish', 'post_title' => "Published {$needle} idea" ) );
		$private = wp_insert_post( array( 'post_type' => 'niroroadmap_item', 'post_status' => 'pending', 'post_title' => "Pending {$needle} idea" ) );
		$locked  = wp_insert_post( array( 'post_type' => 'niroroadmap_item', 'post_status' => 'publish', 'post_title' => "Locked {$needle} idea", 'post_password' => 'secret' ) );
		array_push( $this->item_ids, $public, $private, $locked );

		$found = wp_list_pluck( Submission::similar( $needle ), 'id' );

		$this->assertSame( array( $public ), $found );
		$this->assertSame( array(), Submission::similar( 'ab' ), 'Too short to search.' );
		$this->assertSame( array( 'id', 'title' ), array_keys( Submission::similar( $needle )[0] ), 'Nothing else is exposed.' );
	}

	public function test_a_board_can_override_the_site_default() {
		$this->set( 'submissions_enabled', false );
		$this->assertFalse( Submission::enabled() );
		$this->assertFalse( Submission::enabled( '' ) );
		$this->assertTrue( Submission::enabled( 'yes' ) );
		$this->assertTrue( Submission::enabled( 'YES' ) );

		$this->set( 'submissions_enabled', true );
		$this->assertTrue( Submission::enabled() );
		$this->assertFalse( Submission::enabled( 'no' ) );
		$this->assertTrue( Submission::enabled( 'maybe' ), 'Anything else follows the site default.' );
	}

	public function test_the_board_renders_the_button_only_when_enabled_and_files_under_a_fixed_product() {
		$product = $this->term( 'niroroadmap_product', 'Fixed' );

		$this->set( 'submissions_enabled', false );
		$off = Roadmap::get_roadmap();
		$this->assertStringNotContainsString( 'nr-suggest-btn', $off );
		$this->assertStringNotContainsString( 'id="nr-suggest-overlay"', $off );

		// The dialog is printed once per request, so forget that an earlier test already did.
		unset( $GLOBALS['wp_actions']['niroroadmap_suggest_rendered'] );

		$on = Roadmap::get_roadmap( $product, array( 'submissions' => 'yes' ) );
		$this->assertStringContainsString( 'nr-suggest-btn', $on );
		$this->assertStringContainsString( 'data-product="' . $product . '"', $on );
		$this->assertStringContainsString( 'name="website"', $on, 'Honeypot is in the form.' );
	}

	public function test_a_released_slot_can_be_used_again_and_the_cap_still_holds() {
		$subject = hash( 'sha256', wp_generate_password() );
		$other   = hash( 'sha256', wp_generate_password() );

		$this->assertTrue( \NiroRoadmap\Helper\Rate_Limit::allow( 'submission_test', $subject, 2, 600 ) );
		$this->assertTrue( \NiroRoadmap\Helper\Rate_Limit::allow( 'submission_test', $subject, 2, 600 ) );
		$this->assertFalse( \NiroRoadmap\Helper\Rate_Limit::allow( 'submission_test', $subject, 2, 600 ), 'At the cap.' );

		\NiroRoadmap\Helper\Rate_Limit::release( 'submission_test', $subject, 600 );
		$this->assertTrue( \NiroRoadmap\Helper\Rate_Limit::allow( 'submission_test', $subject, 2, 600 ), 'A refunded slot is usable.' );
		$this->assertFalse( \NiroRoadmap\Helper\Rate_Limit::allow( 'submission_test', $subject, 2, 600 ), 'But the cap is still two.' );

		// Releasing never goes below zero and never touches other subjects.
		\NiroRoadmap\Helper\Rate_Limit::release( 'submission_test', $other, 600 );
		\NiroRoadmap\Helper\Rate_Limit::release( 'submission_test', '', 600 );
		$this->assertTrue( \NiroRoadmap\Helper\Rate_Limit::allow( 'submission_test', $other, 1, 600 ) );
		$this->assertFalse( \NiroRoadmap\Helper\Rate_Limit::allow( 'submission_test', $other, 1, 600 ) );
	}

	public function test_huge_input_is_refused_before_it_is_processed() {
		$before = (int) wp_count_posts( 'niroroadmap_item' )->pending;

		$this->assertSame( 'submission_title_long', $this->submit( array( 'title' => str_repeat( 'x', Submission::max_title() * 8 + 1 ) ) )->get_error_code() );
		$this->assertSame( 'submission_description_long', $this->submit( array( 'description' => str_repeat( 'y', Submission::max_description() * 8 + 1 ) ) )->get_error_code() );

		wp_cache_delete( _count_posts_cache_key( 'niroroadmap_item' ), 'counts' );
		$this->assertSame( $before, (int) wp_count_posts( 'niroroadmap_item' )->pending, 'Nothing was stored.' );
	}

	public function test_defaults_ship_off_with_sensible_values() {
		$this->assertFalse( Settings::default_for( 'submissions_enabled' ) );
		$this->assertFalse( Settings::default_for( 'submissions_login' ) );
		$this->assertSame( 'optional', Settings::default_for( 'submissions_identity' ) );
		$this->assertTrue( Settings::default_for( 'submissions_auto_vote' ) );
		$this->assertTrue( Settings::default_for( 'submissions_notify' ) );
		$this->assertSame( '', Settings::default_for( 'submissions_email' ) );
	}

	public function test_the_notification_email_setting_is_validated() {
		$rejected = Settings::sanitize( array( '_tab' => 'submissions', 'submissions_email' => 'nope' ) );
		$this->assertNotSame( 'nope', $rejected['submissions_email'] ?? null, 'An invalid address is rejected, not stored.' );

		$cleared = Settings::sanitize( array( '_tab' => 'submissions', 'submissions_email' => '' ) );
		$this->assertSame( '', $cleared['submissions_email'] );

		$valid = Settings::sanitize( array( '_tab' => 'submissions', 'submissions_email' => 'ok@example.test' ) );
		$this->assertSame( 'ok@example.test', $valid['submissions_email'] );
	}

	public function test_admin_column_escapes_and_approve_is_for_publishers_of_submissions_only() {
		$this->set( 'submissions_identity', 'required' );
		$id = $this->submit( array( 'name' => '<i>Eve</i>', 'email' => 'eve@example.test' ) )['id'];

		$admin = new \NiroRoadmap\Controller\Admin\Submissions();

		ob_start();
		$admin->show_column( 'niroroadmap_submitter', $id );
		$html = ob_get_clean();
		$this->assertStringNotContainsString( '<i>', $html );
		$this->assertStringContainsString( 'eve@example.test', $html );

		// A visitor submission gets Approve for people who can publish it; nobody else does.
		wp_set_current_user( $this->user( 'administrator' ) );
		$actions = $admin->add_approve_action( array( 'edit' => 'x' ), get_post( $id ) );
		$this->assertArrayHasKey( 'niroroadmap_approve', $actions );
		$this->assertStringContainsString( '_wpnonce=', $actions['niroroadmap_approve'] );

		wp_set_current_user( $this->user( 'subscriber' ) );
		$this->assertArrayNotHasKey( 'niroroadmap_approve', $admin->add_approve_action( array( 'edit' => 'x' ), get_post( $id ) ) );

		// Not for items the team created themselves.
		wp_set_current_user( $this->user( 'administrator' ) );
		$own = wp_insert_post( array( 'post_type' => 'niroroadmap_item', 'post_status' => 'pending', 'post_title' => 'Team draft' ) );
		$this->item_ids[] = $own;
		$this->assertArrayNotHasKey( 'niroroadmap_approve', $admin->add_approve_action( array(), get_post( $own ) ) );
	}
}
