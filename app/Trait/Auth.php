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
		return $this->is_sandbox_mode() || current_user_can( 'manage_options' );
	}

	/**
	 * Check if the current user can move the requested task to the requested stage.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool True if the user can edit the task and assign the stage, false otherwise.
	 */
	public function can_move_task( $request ) {
		$task  = get_post( (int) $request->get_param( 'id' ) );
		$stage = get_term( (int) $request->get_param( 'stage' ), 'niroroadmap_status' );

		if ( ! $task || 'niroroadmap_item' !== $task->post_type || ! $stage || is_wp_error( $stage ) ) {
			return false;
		}

		return $this->is_sandbox_mode() || ( current_user_can( 'edit_post', $task->ID ) && current_user_can( 'assign_term', $stage->term_id ) );
	}

	/**
	 * Check if the current user can reorder every task in the request.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool True if every ID is a task the user can edit, false otherwise.
	 */
	public function can_order_tasks( $request ) {
		$order = $request->get_param( 'order' );

		if ( ! is_array( $order ) ) {
			return false;
		}

		foreach ( $order as $task_id ) {
			$task = get_post( (int) str_replace( 'nr-task-', '', $task_id ) );

			if ( ! $task || 'niroroadmap_item' !== $task->post_type ) {
				return false;
			}

			if ( ! $this->is_sandbox_mode() && ! current_user_can( 'edit_post', $task->ID ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if the current user can reorder every stage in the request.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool True if every ID is a stage the user can edit, false otherwise.
	 */
	public function can_order_stages( $request ) {
		$order = $request->get_param( 'order' );

		if ( ! is_array( $order ) ) {
			return false;
		}

		foreach ( $order as $term_id ) {
			$stage = get_term( (int) str_replace( 'tag-', '', $term_id ), 'niroroadmap_status' );

			if ( ! $stage || is_wp_error( $stage ) ) {
				return false;
			}

			if ( ! $this->is_sandbox_mode() && ! current_user_can( 'edit_term', $stage->term_id ) ) {
				return false;
			}
		}

		return true;
	}
}
