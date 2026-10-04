<?php
namespace NiroRoadmap\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Fixed-window rate limit for anonymous requests, kept in transients.
 *
 * Meant to be shared by every public endpoint that needs it (voting, and later submissions and
 * comments). The subject is a hash, never a raw IP.
 *
 * Counting is serialised per subject with a MySQL named lock. Without it, a burst of parallel
 * requests all read the same count and the limit stops meaning anything, which is exactly the
 * traffic a rate limit is for. The lock is only held for the read-and-update, and only requests
 * from the same subject wait on each other.
 */
class Rate_Limit {

	/**
	 * Count a request and say whether it is allowed.
	 *
	 * @param string $bucket  What is limited, e.g. `vote`.
	 * @param string $subject Hash identifying the requester. Empty means unknown, which isn't limited.
	 * @param int    $limit   Requests allowed per window.
	 * @param int    $window  Window length in seconds.
	 * @return bool True if the request may go ahead.
	 */
	public static function allow( $bucket, $subject, $limit, $window ) {
		if ( '' === $subject || $limit < 1 ) {
			return true;
		}

		global $wpdb;

		$key  = 'niroroadmap_rl_' . md5( $bucket . '|' . $subject );
		$lock = substr( $key, 0, 47 ); // Lock names are limited to 64 characters.

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Named lock, no table involved.
		$locked = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 2 )', $lock ) );

		// Couldn't get in line: this requester is already hammering us. Deny rather than skip counting.
		if ( '1' !== $locked ) {
			return false;
		}

		try {
			return self::count( $key, $limit, $window );
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Named lock, no table involved.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock ) );
		}
	}

	/**
	 * Read, check and update the window. Only call while holding the lock.
	 */
	private static function count( $key, $limit, $window ) {
		$state = get_transient( $key );
		$now   = time();

		if ( ! is_array( $state ) || ! isset( $state['count'], $state['start'] ) || $now - $state['start'] >= $window ) {
			$state = array(
				'count' => 0,
				'start' => $now,
			);
		}

		if ( $state['count'] >= $limit ) {
			return false;
		}

		++$state['count'];

		// Expire with the window, not a fresh one, so steady traffic can't keep extending it.
		set_transient( $key, $state, max( 1, $window - ( $now - $state['start'] ) ) );

		return true;
	}
}
