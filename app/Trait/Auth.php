<?php
namespace NiroRoadmap\Trait;

defined( 'ABSPATH' ) || exit;

trait Auth {

	/**
	 * Check if sandbox/test mode is enabled.
	 *
	 * @return bool True if sandbox mode is enabled, false otherwise.
	 */
	protected function is_sandbox_mode() {
		return defined( 'NIROROADMAP_SANDBOX' ) && NIROROADMAP_SANDBOX;
	}

	/**
	 * Verifies if it's a human user, not bots
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool True for regular cases, false otherwise.
	 */
	public function is_user( $request ) {
		return __return_true();
	}

	/**
	 * Check if the current user is a guest (not logged in).
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool True if sandbox mode is disabled and the user is not logged in, false otherwise.
	 */
	public function is_guest( $request ) {
		return ! $this->is_sandbox_mode() && ! is_user_logged_in();
	}

	/**
	 * Check if the current user is a member.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool True if sandbox mode is enabled or the user is logged in, false otherwise.
	 */
	public function is_member( $request ) {
		return $this->is_sandbox_mode() || is_user_logged_in();
	}

	/**
	 * Check if the current user is an editor.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool True if sandbox mode is enabled or the user has editor capabilities, false otherwise.
	 */
	public function is_editor( $request ) {
		return $this->is_sandbox_mode() || current_user_can( 'edit_pages' );
	}

	/**
	 * Check if the current user is an administrator.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool True if sandbox mode is enabled or the user has administrator capabilities, false otherwise.
	 */
	public function is_admin( $request ) {
		return $this->is_sandbox_mode() || current_user_can( 'administrator' );
	}
}
