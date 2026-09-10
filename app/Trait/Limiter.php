<?php
namespace NiroRoadmap\Trait;

defined( 'ABSPATH' ) || exit;

/**
 * Trait Limiter
 *
 * This trait provides methods to implement rate limiting for user requests in the WordPress plugin.
 *
 * @package NiroRoadmap
 */
trait Limiter {

	/**
	 * Meta key for the request count.
	 *
	 * @var string
	 */
	private $count_key = '_request_count';

	/**
	 * Meta key for the first request time.
	 *
	 * @var string
	 */
	private $time_key = '_first_request_time';

	/**
	 * Check the rate limit for a user.
	 *
	 * @param int $user_id The user ID.
	 * @param int $limit The maximum number of requests allowed within the interval. Default is 100.
	 * @param int $interval The time interval in seconds within which the requests are limited. Default is 3600 (1 hour).
	 *
	 * @return bool True if the request is within the limit, false if the limit is exceeded.
	 */
	private function check_rate_limit( $user_id, $limit = 100, $interval = 3600 ) {
		$request_count      = get_user_meta( $user_id, $this->count_key, true );
		$first_request_time = get_user_meta( $user_id, $this->time_key, true );

		// If there is no recorded first request time, set it to the current time
		if ( ! $first_request_time ) {
			$first_request_time = date_i18n( 'U' );
			update_user_meta( $user_id, $this->time_key, $first_request_time );
		}

		// If the current time exceeds the interval since the first request, reset the count and time
		if ( date_i18n( 'U' ) - $first_request_time > $interval ) {
			update_user_meta( $user_id, $this->count_key, 1 );
			update_user_meta( $user_id, $this->time_key, date_i18n( 'U' ) );
		} else {
			// If the request count exceeds the limit, deny the request
			if ( $request_count >= $limit ) {
				return false;
			} else {
				// Increment the request count
				update_user_meta( $user_id, $this->count_key, $request_count + 1 );
			}
		}

		return true;
	}
}
