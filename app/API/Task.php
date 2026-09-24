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
	 * Get a task details
	 */
	public function get( $request ) {
		$task = $this->get_public_task( $request->get_param( 'id' ) );

		$this->response_success(
			array(
				'message' => __( 'Task found', 'niroroadmap' ),
				'task'    => array(
					'title'       => $task->post_title,
					'description' => wpautop( $task->post_content ),
					'upvotes'     => get_post_meta( $task->ID, 'upvote', true ),
					'downvotes'   => get_post_meta( $task->ID, 'downvote', true ),
				),
			)
		);
	}

	public function vote( $request ) {
		$id   = $this->get_public_task( $request->get_param( 'id' ) )->ID;
		$type = $request->get_param( 'type' );

		$current_vote = get_post_meta( $id, $type, true );
		$new_vote     = (int) $current_vote + 1;

		update_post_meta( $id, $type, $new_vote );

		$this->response_success(
			array(
				'message' => __( 'Vote submitted', 'niroroadmap' ),
				'votes'   => $new_vote,
			)
		);
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
