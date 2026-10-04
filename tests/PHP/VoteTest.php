<?php
use NiroRoadmap\Helper\Rate_Limit;
use NiroRoadmap\Helper\Voter;
use NiroRoadmap\Model\Vote;
use PHPUnit\Framework\TestCase;

/**
 * Server-side vote integrity: one vote per visitor per item, vote changing, counters, rate limit.
 */
class VoteTest extends TestCase {

	private $item_ids = array();

	private $user_ids = array();

	protected function setUp(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php'; // wp_delete_user().
		Vote::maybe_create_table();
		unset( $_COOKIE[ Voter::COOKIE ] );
		$_SERVER['REMOTE_ADDR']     = '203.0.113.7';
		$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		foreach ( $this->item_ids as $id ) {
			wp_delete_post( $id, true ); // Also removes its vote rows through the deleted_post hook.
		}
		wp_set_current_user( 0 );
		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		remove_all_filters( 'niroroadmap_vote_fingerprint' );
	}

	private function item( $upvotes = null ) {
		$id = wp_insert_post(
			array(
				'post_type'   => 'niroroadmap_item',
				'post_status' => 'publish',
				'post_title'  => 'Vote test ' . wp_generate_password( 6, false ),
			)
		);

		if ( null !== $upvotes ) {
			update_post_meta( $id, 'upvote', $upvotes );
		}

		return $this->item_ids[] = $id;
	}

	/**
	 * An anonymous visitor: own cookie token, and an IP + user agent.
	 */
	private function visitor( $token, $ip = '203.0.113.7', $ua = 'PHPUnit' ) {
		$_COOKIE[ Voter::COOKIE ]   = $token;
		$_SERVER['REMOTE_ADDR']     = $ip;
		$_SERVER['HTTP_USER_AGENT'] = $ua;

		return Voter::identify();
	}

	private function token() {
		return bin2hex( random_bytes( 16 ) );
	}

	private function rows( $item ) {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Vote::table() . ' WHERE item_id = %d', $item ) );
	}

	public function test_first_vote_is_counted_and_recorded() {
		$item   = $this->item();
		$result = Vote::cast( $item, 'upvote', $this->visitor( $this->token() ), false );

		$this->assertSame( 'created', $result['status'] );
		$this->assertSame( array( 'upvote' => 1, 'downvote' => 0 ), Vote::counts( $item ) );
		$this->assertSame( 1, $this->rows( $item ) );
	}

	public function test_existing_counters_survive_and_keep_counting() {
		$item = $this->item( 41 );
		Vote::cast( $item, 'upvote', $this->visitor( $this->token() ), false );

		$this->assertSame( 42, Vote::counts( $item )['upvote'] );
	}

	public function test_same_voter_cannot_vote_twice_even_with_local_storage_cleared() {
		$item  = $this->item();
		$voter = $this->visitor( $this->token() );

		Vote::cast( $item, 'upvote', $voter, false );
		$again = Vote::cast( $item, 'upvote', $voter, false );

		$this->assertSame( 'duplicate', $again['status'] );
		$this->assertSame( 'upvote', $again['vote'] );
		$this->assertSame( 1, Vote::counts( $item )['upvote'] );
		$this->assertSame( 1, $this->rows( $item ) );
	}

	public function test_deleting_cookies_from_same_ip_and_user_agent_is_still_a_duplicate() {
		$item = $this->item();

		Vote::cast( $item, 'upvote', $this->visitor( $this->token() ), false );
		$after_clearing_cookies = Vote::cast( $item, 'upvote', $this->visitor( $this->token() ), false );

		$this->assertSame( 'duplicate', $after_clearing_cookies['status'] );
		$this->assertSame( 1, Vote::counts( $item )['upvote'] );
	}

	public function test_a_different_visitor_can_vote() {
		$item = $this->item();

		Vote::cast( $item, 'upvote', $this->visitor( $this->token(), '203.0.113.7' ), false );
		$other = Vote::cast( $item, 'upvote', $this->visitor( $this->token(), '198.51.100.9' ), false );

		$this->assertSame( 'created', $other['status'] );
		$this->assertSame( 2, Vote::counts( $item )['upvote'] );
	}

	public function test_the_same_visitor_can_vote_on_another_item() {
		$a     = $this->item();
		$b     = $this->item();
		$voter = $this->visitor( $this->token() );

		Vote::cast( $a, 'upvote', $voter, false );

		$this->assertSame( 'created', Vote::cast( $b, 'upvote', $voter, false )['status'] );
	}

	public function test_turning_the_fingerprint_off_leaves_cookie_only_dedup() {
		add_filter( 'niroroadmap_vote_fingerprint', '__return_empty_string' );
		$item = $this->item();

		Vote::cast( $item, 'upvote', $this->visitor( $this->token() ), false );
		$new_cookie = Vote::cast( $item, 'upvote', $this->visitor( $this->token() ), false );

		$this->assertSame( 'created', $new_cookie['status'] );
	}

	public function test_logged_in_vote_follows_the_user_across_devices() {
		$item = $this->item();
		$user = $this->factory_user();

		wp_set_current_user( $user );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		Vote::cast( $item, 'upvote', Voter::identify(), false );

		$_SERVER['REMOTE_ADDR']     = '198.51.100.50'; // Another device, another network.
		$_SERVER['HTTP_USER_AGENT'] = 'Other browser';
		$again                      = Vote::cast( $item, 'upvote', Voter::identify(), false );

		$this->assertSame( 'duplicate', $again['status'] );
		$this->assertSame( 1, Vote::counts( $item )['upvote'] );

	}

	public function test_two_users_on_the_same_device_can_both_vote() {
		$item = $this->item();
		$a    = $this->factory_user();
		$b    = $this->factory_user();

		wp_set_current_user( $a );
		Vote::cast( $item, 'upvote', Voter::identify(), false );
		wp_set_current_user( $b );
		$second = Vote::cast( $item, 'upvote', Voter::identify(), false );

		$this->assertSame( 'created', $second['status'] );
		$this->assertSame( 2, Vote::counts( $item )['upvote'] );

	}

	public function test_vote_is_final_when_changing_is_off() {
		$item  = $this->item();
		$voter = $this->visitor( $this->token() );

		Vote::cast( $item, 'upvote', $voter, false );
		$switch = Vote::cast( $item, 'downvote', $voter, false );

		$this->assertSame( 'duplicate', $switch['status'] );
		$this->assertSame( 'upvote', $switch['vote'] );
		$this->assertSame( array( 'upvote' => 1, 'downvote' => 0 ), Vote::counts( $item ) );
	}

	public function test_changing_a_vote_moves_both_counters_when_on() {
		$item  = $this->item();
		$voter = $this->visitor( $this->token() );

		Vote::cast( $item, 'upvote', $voter, true );
		$switch = Vote::cast( $item, 'downvote', $voter, true );

		$this->assertSame( 'changed', $switch['status'] );
		$this->assertSame( 'downvote', $switch['vote'] );
		$this->assertSame( array( 'upvote' => 0, 'downvote' => 1 ), Vote::counts( $item ) );
		$this->assertSame( 1, $this->rows( $item ) );
	}

	public function test_repeating_the_same_vote_is_a_duplicate_even_when_changing_is_on() {
		$item  = $this->item();
		$voter = $this->visitor( $this->token() );

		Vote::cast( $item, 'upvote', $voter, true );

		$this->assertSame( 'duplicate', Vote::cast( $item, 'upvote', $voter, true )['status'] );
		$this->assertSame( 1, Vote::counts( $item )['upvote'] );
	}

	public function test_a_counter_never_goes_below_zero() {
		$item  = $this->item( 0 );
		$voter = $this->visitor( $this->token() );

		Vote::cast( $item, 'upvote', $voter, true );
		update_post_meta( $item, 'upvote', 0 ); // Counter was reset by hand, row still says upvote.
		Vote::cast( $item, 'downvote', $voter, true );

		$this->assertSame( 0, Vote::counts( $item )['upvote'] );
	}

	public function test_votes_rows_are_removed_with_the_item() {
		$item = $this->item();
		Vote::cast( $item, 'upvote', $this->visitor( $this->token() ), false );

		wp_delete_post( $item, true );

		$this->assertSame( 0, $this->rows( $item ) );
		$this->item_ids = array();
	}

	public function test_nothing_stored_in_the_clear() {
		global $wpdb;

		$item = $this->item();
		Vote::cast( $item, 'upvote', $this->visitor( $this->token(), '203.0.113.7', 'PHPUnit' ), false );

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Vote::table() . ' WHERE item_id = %d', $item ), ARRAY_A );

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $row['voter_hash'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $row['fingerprint_hash'] );
		$this->assertStringNotContainsString( '203.0.113.7', implode( '|', $row ) );
	}

	public function test_an_unknown_vote_type_is_refused_and_writes_nothing() {
		$item = $this->item();

		$this->expectException( InvalidArgumentException::class );

		try {
			Vote::cast( $item, 'evil_meta_key', $this->visitor( $this->token() ), false );
		} finally {
			$this->assertSame( 0, $this->rows( $item ) );
			$this->assertSame( '', get_post_meta( $item, 'evil_meta_key', true ) );
		}
	}

	public function test_logged_in_vote_stores_a_real_null_fingerprint() {
		global $wpdb;

		$item = $this->item();
		wp_set_current_user( $this->factory_user() );
		Vote::cast( $item, 'upvote', Voter::identify(), false );

		$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SELECT fingerprint_hash FROM ' . Vote::table() . ' WHERE item_id = %d', $item ) ) );
	}

	public function test_voter_cookie_must_be_well_formed() {
		$_COOKIE[ Voter::COOKIE ] = 'not-a-token';

		$this->assertSame( '', Voter::identify()['hash'] );
	}

	public function test_rate_limit_key_groups_ipv6_by_its_64_block_and_keeps_ipv4_apart() {
		$key = function ( $ip ) {
			$_SERVER['REMOTE_ADDR'] = $ip;

			return Voter::ip_hash();
		};

		// Same /64: one visitor stepping through their own addresses.
		$this->assertSame( $key( '2001:db8:abcd:12::1' ), $key( '2001:db8:abcd:12:ffff:ffff:ffff:ffff' ) );
		// Different /64: different customers.
		$this->assertNotSame( $key( '2001:db8:abcd:12::1' ), $key( '2001:db8:abcd:13::1' ) );
		// IPv4 is per address, as written plainly or as an IPv4-mapped IPv6 address.
		$this->assertNotSame( $key( '203.0.113.7' ), $key( '203.0.113.8' ) );
		$this->assertSame( $key( '203.0.113.7' ), $key( '::ffff:203.0.113.7' ) );
		$this->assertNotSame( $key( '::ffff:203.0.113.7' ), $key( '::ffff:203.0.113.8' ) );
		// Unknown stays unknown.
		$this->assertSame( '', $key( 'not-an-ip' ) );
	}

	public function test_rate_limit_blocks_after_the_limit_and_isolates_subjects() {
		$subject = hash( 'sha256', wp_generate_password() );
		$other   = hash( 'sha256', wp_generate_password() );

		$this->assertTrue( Rate_Limit::allow( 'test', $subject, 3, 600 ) );
		$this->assertTrue( Rate_Limit::allow( 'test', $subject, 3, 600 ) );
		$this->assertTrue( Rate_Limit::allow( 'test', $subject, 3, 600 ) );
		$this->assertFalse( Rate_Limit::allow( 'test', $subject, 3, 600 ) );
		$this->assertTrue( Rate_Limit::allow( 'test', $other, 3, 600 ) );
		$this->assertTrue( Rate_Limit::allow( 'test', '', 1, 600 ), 'An unknown requester is not limited.' );
	}

	private function factory_user() {
		return $this->user_ids[] = wp_insert_user(
			array(
				'user_login' => 'votetest_' . wp_generate_password( 8, false ),
				'user_pass'  => wp_generate_password(),
				'user_email' => wp_generate_password( 8, false ) . '@example.test',
			)
		);
	}
}
