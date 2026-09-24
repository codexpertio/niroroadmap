<?php
namespace NiroRoadmap\API;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Trait\Rest;

class Option {

	use Rest;

	/**
	 * Only the plugin's own options can be read or changed through the API. Anything else
	 * (e.g. `active_plugins`, `siteurl`) could break or take over the site.
	 *
	 * @param mixed $key
	 * @return bool
	 */
	private function is_allowed_key( $key ) {
		return is_string( $key ) && 0 === strpos( $key, 'niroroadmap_' );
	}


	/**
	 * Get the value of a specified option.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get( $request ) {
		$key = $request->get_param( 'key' );

		if ( empty( $key ) ) {
			return $this->response_error( __( 'Option key is required.', 'niroroadmap' ) );
		}

		if ( ! $this->is_allowed_key( $key ) ) {
			return $this->response_error( __( 'This option cannot be changed.', 'niroroadmap' ), 403 );
		}

		$value = get_option( $key );

		return $this->response_success( $value );
	}

	/**
	 * Update the value of a specified option.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function update( $request ) {
		$key   = $request->get_param( 'key' );
		$value = $request->get_param( 'value' );

		if ( empty( $key ) || empty( $value ) ) {
			return $this->response_error( __( 'Option key and value are required.', 'niroroadmap' ) );
		}

		if ( ! $this->is_allowed_key( $key ) ) {
			return $this->response_error( __( 'This option cannot be changed.', 'niroroadmap' ), 403 );
		}

		$updated = update_option( $key, $value );

		if ( ! $updated ) {
			return $this->response_success( __( 'Option not updated.', 'niroroadmap' ) );
		}

		return $this->response_success( __( 'Option updated successfully.', 'niroroadmap' ) );
	}

	/**
	 * Delete the specified option.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function delete( $request ) {
		$key = $request->get_param( 'key' );

		if ( empty( $key ) ) {
			return $this->response_error( __( 'Option key is required.', 'niroroadmap' ) );
		}

		if ( ! $this->is_allowed_key( $key ) ) {
			return $this->response_error( __( 'This option cannot be changed.', 'niroroadmap' ), 403 );
		}

		$deleted = delete_option( $key );

		if ( ! $deleted ) {
			return $this->response_error( __( 'Failed to delete option.', 'niroroadmap' ) );
		}

		return $this->response_success( __( 'Option deleted successfully.', 'niroroadmap' ) );
	}
}
