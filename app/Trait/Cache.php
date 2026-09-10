<?php
namespace NiroRoadmap\Trait;

defined( 'ABSPATH' ) || exit;

/**
 * Trait Cache
 *
 * This trait provides methods for setting, retrieving, and deleting cache values using either an external object cache or WordPress transients.
 *
 * @package NiroRoadmap
 */
trait Cache {

	/**
	 * Checks if external object cache is in use and returns true or false.
	 *
	 * @return bool Returns true if external object cache is used, otherwise false.
	 */
	private function is_using_object_cache() {
		return wp_using_ext_object_cache();
	}

	/**
	 * Sets a cache value using either object cache or transients based on availability.
	 *
	 * @param string $key The cache key to store the value under.
	 * @param mixed  $value The value to store in the cache.
	 * @param int    $expiration Optional. The time until expiration, in seconds. Default 3600 (1 hour).
	 */
	public function set_cache( $key, $value, $expiration = 3600 ) {
		if ( $this->is_using_object_cache() ) {
			wp_cache_set( $key, $value, '', $expiration );
		} else {
			set_transient( $key, $value, $expiration );
		}
	}

	/**
	 * Retrieves a cache value using either object cache or transients based on availability.
	 *
	 * @param string $key The cache key to retrieve.
	 * @return mixed The cached value, or false if the value is not found or is expired.
	 */
	public function get_cache( $key ) {
		if ( $this->is_using_object_cache() ) {
			return wp_cache_get( $key );
		}

		return get_transient( $key );
	}

	/**
	 * Deletes a cache value using either object cache or transients based on availability.
	 *
	 * @param string $key The cache key whose value should be deleted.
	 */
	public function delete_cache( $key ) {
		if ( $this->is_using_object_cache() ) {
			wp_cache_delete( $key );
		} else {
			delete_transient( $key );
		}
	}
}
