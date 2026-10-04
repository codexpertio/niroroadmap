<?php
namespace NiroRoadmap\Model;

defined( 'ABSPATH' ) || exit;

/**
 * One row per voter per item, plus the cached `upvote` / `downvote` counters on the item.
 *
 * The table is what makes votes trustworthy: unique indexes let the database, not PHP, decide
 * who voted first, so simultaneous requests can't double-count. The post meta counters stay as
 * the fast, display-side numbers (and keep the votes from before the table existed).
 */
class Vote {

	const SCHEMA_VERSION = '1';

	const TYPES = array( 'upvote', 'downvote' );

	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'niroroadmap_votes';
	}

	/**
	 * Create or update the table. Safe to run on every load: it does nothing once up to date.
	 */
	public static function maybe_create_table() {
		if ( self::SCHEMA_VERSION === get_option( 'niroroadmap_schema_version' ) ) {
			return;
		}

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		// `fingerprint_hash` is NULL for logged-in voters; NULLs don't collide in a unique index.
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				item_id bigint(20) unsigned NOT NULL,
				voter_hash char(64) NOT NULL,
				fingerprint_hash char(64) DEFAULT NULL,
				type varchar(8) NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY item_voter (item_id,voter_hash),
				UNIQUE KEY item_fingerprint (item_id,fingerprint_hash),
				KEY voter_hash (voter_hash)
			) {$charset};"
		);

		// Only mark it done if the table really exists, so a failed create is retried.
		if ( self::table_exists() ) {
			update_option( 'niroroadmap_schema_version', self::SCHEMA_VERSION );
		}
	}

	private static function table_exists() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
		return self::table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( self::table() ) ) );
	}

	/**
	 * The vote this voter already cast on an item, if any.
	 *
	 * Matches by voter hash, or by fingerprint, so a visitor who cleared their cookies is still
	 * recognised.
	 *
	 * @param int    $item_id     Item ID.
	 * @param string $hash        Voter hash (may be empty).
	 * @param string $fingerprint Fingerprint hash (may be empty).
	 * @return object|null Row with `id` and `type`, or null.
	 */
	public static function find( $item_id, $hash, $fingerprint ) {
		global $wpdb;

		$table = self::table();

		if ( '' === $hash && '' === $fingerprint ) {
			return null;
		}

		$where = array();
		$args  = array( (int) $item_id );

		if ( '' !== $hash ) {
			$where[] = 'voter_hash = %s';
			$args[]  = $hash;
		}

		if ( '' !== $fingerprint ) {
			$where[] = 'fingerprint_hash = %s';
			$args[]  = $fingerprint;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is ours; the OR list is built from fixed fragments.
		return $wpdb->get_row( $wpdb->prepare( "SELECT id, type FROM {$table} WHERE item_id = %d AND ( " . implode( ' OR ', $where ) . ' ) LIMIT 1', $args ) );
	}

	/**
	 * Cast a vote.
	 *
	 * @param int    $item_id      Published roadmap item.
	 * @param string $type         `upvote` or `downvote`.
	 * @param array  $voter        Result of Voter::identify(), with a non-empty `hash`.
	 * @param bool   $allow_change Whether an existing vote may be switched to the other type.
	 * @return array {
	 *     @type string $status `created`, `changed` or `duplicate`.
	 *     @type string $vote   The voter's vote on the item after this call.
	 * }
	 */
	public static function cast( $item_id, $type, $voter, $allow_change ) {
		// The type becomes a post meta key in bump(), so it must never be anything else.
		if ( ! in_array( $type, self::TYPES, true ) ) {
			throw new \InvalidArgumentException( 'Unknown vote type.' );
		}

		$item_id = (int) $item_id;

		$existing = self::find( $item_id, $voter['hash'], $voter['fingerprint'] );

		if ( ! $existing ) {
			if ( self::insert( $item_id, $type, $voter ) ) {
				self::bump( $item_id, $type, 1 );

				return array(
					'status' => 'created',
					'vote'   => $type,
				);
			}

			// Lost a race with this voter's own simultaneous request.
			$existing = self::find( $item_id, $voter['hash'], $voter['fingerprint'] );
		}

		if ( $existing && $allow_change && $existing->type !== $type && self::switch_type( (int) $existing->id, $existing->type, $type ) ) {
			self::bump( $item_id, $existing->type, -1 );
			self::bump( $item_id, $type, 1 );

			return array(
				'status' => 'changed',
				'vote'   => $type,
			);
		}

		return array(
			'status' => 'duplicate',
			'vote'   => $existing ? $existing->type : $type,
		);
	}

	/**
	 * Insert a vote row. The unique indexes make this succeed for exactly one of any number of
	 * simultaneous attempts by the same voter.
	 *
	 * @return bool True if the row was inserted.
	 */
	private static function insert( $item_id, $type, $voter ) {
		global $wpdb;

		$table = self::table();
		$now   = gmdate( 'Y-m-d H:i:s' );

		// A logged-in voter has no fingerprint: store a real NULL, which the unique index ignores.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is ours; values are placeholders.
		if ( '' === $voter['fingerprint'] ) {
			$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} ( item_id, voter_hash, fingerprint_hash, type, created_at ) VALUES ( %d, %s, NULL, %s, %s )", $item_id, $voter['hash'], $type, $now ) );
		} else {
			$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} ( item_id, voter_hash, fingerprint_hash, type, created_at ) VALUES ( %d, %s, %s, %s, %s )", $item_id, $voter['hash'], $voter['fingerprint'], $type, $now ) );
		}
		// phpcs:enable

		return 1 === $inserted;
	}

	/**
	 * Move a vote to the other type. Only succeeds if it still has the old type, so two
	 * simultaneous switches can't both adjust the counters.
	 */
	private static function switch_type( $row_id, $from, $to ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic compare-and-set.
		return 1 === $wpdb->update( self::table(), array( 'type' => $to ), array( 'id' => $row_id, 'type' => $from ), array( '%s' ), array( '%d', '%s' ) );
	}

	/**
	 * Add to a counter in one SQL statement, so simultaneous votes can't overwrite each other.
	 *
	 * @param int    $item_id Item ID.
	 * @param string $type    `upvote` or `downvote`.
	 * @param int    $delta   +1 or -1. Counters never go below zero.
	 */
	private static function bump( $item_id, $type, $delta ) {
		global $wpdb;

		// Items that never got a vote have no meta row yet. `unique` makes this a no-op otherwise.
		add_post_meta( $item_id, $type, 0, true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic counter update.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = GREATEST( CAST( meta_value AS SIGNED ) + %d, 0 ) WHERE post_id = %d AND meta_key = %s", $delta, $item_id, $type ) );

		wp_cache_delete( $item_id, 'post_meta' );
	}

	/**
	 * Current counters of an item.
	 *
	 * @return array{upvote:int,downvote:int}
	 */
	public static function counts( $item_id ) {
		wp_cache_delete( (int) $item_id, 'post_meta' );

		return array(
			'upvote'   => (int) get_post_meta( $item_id, 'upvote', true ),
			'downvote' => (int) get_post_meta( $item_id, 'downvote', true ),
		);
	}

	/**
	 * Remove the vote rows of a deleted item.
	 */
	public static function delete_for_item( $item_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup.
		$wpdb->delete( self::table(), array( 'item_id' => (int) $item_id ), array( '%d' ) );
	}
}
