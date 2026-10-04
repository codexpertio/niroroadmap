<?php
namespace NiroRoadmap\API;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Trait\Rest;

class Task {

	use Rest;

	/**
	 * Move a task to a stage
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function move( $request ) {
		$task  = $request->get_param( 'id' );
		$stage = $request->get_param( 'stage' );

		wp_set_post_terms( $task, array( $stage ), 'niroroadmap_status' );

		$this->response_success( array( 'message' => __( 'Task moved', 'niroroadmap' ) ) );
	}

	/**
	 * Only published, non-password-protected roadmap items are public.
	 *
	 * @param int $id
	 * @return WP_Post
	 */
	private function get_public_task( $id ) {
		$task = get_post( (int) $id );

		if ( ! $task || 'niroroadmap_item' !== $task->post_type || 'publish' !== $task->post_status || '' !== $task->post_password ) {
			$this->response_error( array( 'message' => __( 'Task not found', 'niroroadmap' ) ), 404 );
		}

		return $task;
	}

	/**
	 * Whether the current visitor may see the count of a vote type.
	 *
	 * Counts that must stay hidden are left out of API responses, not just hidden on the page.
	 *
	 * @param string $type `upvote` or `downvote`.
	 * @return bool
	 */
	private function can_see_count( $type ) {
		if ( ! niroroadmap_get_setting( 'show_vote_counts' ) ) {
			return false;
		}

		if ( 'downvote' !== $type ) {
			return true;
		}

		return niroroadmap_get_setting( 'show_downvote' ) && ( 'admins' !== niroroadmap_get_setting( 'show_downvotes_to' ) || current_user_can( 'manage_options' ) );
	}

	/**
	 * Get a task details
	 */
	public function get( $request ) {
		$task = $this->get_public_task( $request->get_param( 'id' ) );

		$data = array(
			'title'       => $task->post_title,
			'description' => wpautop( $task->post_content ),
		);

		foreach ( array( 'upvote', 'downvote' ) as $type ) {
			if ( $this->can_see_count( $type ) ) {
				$data[ $type . 's' ] = get_post_meta( $task->ID, $type, true );
			}
		}

		$this->response_success(
			array(
				'message' => __( 'Task found', 'niroroadmap' ),
				'task'    => $data,
			)
		);
	}

	public function vote( $request ) {
		$id   = $this->get_public_task( $request->get_param( 'id' ) )->ID;
		$type = $request->get_param( 'type' );

		if ( 'logged_in' === niroroadmap_get_setting( 'vote_who' ) && ! is_user_logged_in() ) {
			$this->response_error( array( 'message' => __( 'Please log in to vote.', 'niroroadmap' ) ), 401 );
		}

		if ( 'downvote' === $type && ! niroroadmap_get_setting( 'show_downvote' ) ) {
			$this->response_error( array( 'message' => __( 'Downvoting is turned off.', 'niroroadmap' ) ), 403 );
		}

		$current_vote = get_post_meta( $id, $type, true );
		$new_vote     = (int) $current_vote + 1;

		update_post_meta( $id, $type, $new_vote );

		$data = array( 'message' => __( 'Vote submitted', 'niroroadmap' ) );

		if ( $this->can_see_count( $type ) ) {
			$data['votes'] = $new_vote;
		}

		$this->response_success( $data );
	}

	/**
	 * Sort task orders
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function order( $request ) {
		$order = $request->get_param( 'order' );

		foreach ( $order as $position => $task_id ) {
			$task_id = (int) str_replace( 'nr-task-', '', $task_id );

			// update_post_meta( $task_id, 'menu_order', $position );
			wp_update_post(
				array(
					'ID'         => $task_id,
					'menu_order' => $position,
				)
			);
		}

		$this->response_success( array( 'message' => __( 'Task order changed', 'niroroadmap' ) ) );
	}
}
