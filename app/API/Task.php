<?php
namespace NiroRoadmap\API;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Helper\Rate_Limit;
use NiroRoadmap\Helper\Voter;
use NiroRoadmap\Model\Fields;
use NiroRoadmap\Model\Roadmap;
use NiroRoadmap\Model\Status_Log;
use NiroRoadmap\Model\Vote;
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
		$task = Roadmap::get_public_item( $id );

		if ( ! $task ) {
			$this->response_error( array( 'message' => __( 'Task not found', 'niroroadmap' ) ), 404 );
		}

		return $task;
	}

	/**
	 * Whether the current visitor may see the count of a vote type.
	 *
	 * Counts that must stay hidden are left out of API responses, not just hidden on the page.
	 *
	 * @param string $type    `upvote` or `downvote`.
	 * @param int    $item_id Item ID: an item can have its counts switched off on its own.
	 * @return bool
	 */
	private function can_see_count( $type, $item_id ) {
		if ( ! niroroadmap_get_setting( 'show_vote_counts' ) || Fields::votes_hidden( $item_id ) ) {
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

		// The response says what *this* visitor voted, so it must never be served from a shared cache.
		nocache_headers();

		$voter = Voter::identify();
		$own   = Vote::find( $task->ID, $voter['hash'], $voter['fingerprint'] );

		$data = array(
			'title'       => $task->post_title,
			'description' => wpautop( $task->post_content ),
			'voted'       => $own ? $own->type : null,
		);

		$data += $this->visible_counts( $task->ID );

		// Cover image, target, release, version and link: only what is public (see Fields::public_facts()).
		$data += Fields::public_facts( $task );

		// The status timeline, if the site shows it.
		if ( niroroadmap_get_setting( 'show_history' ) ) {
			$timeline = Status_Log::timeline( $task->ID );

			if ( count( $timeline ) > 1 ) {
				$data['history'] = $timeline;
			}
		}

		$this->response_success(
			array(
				'message' => __( 'Task found', 'niroroadmap' ),
				'task'    => $data,
			)
		);
	}

	/**
	 * The counts this visitor is allowed to see, as `upvotes` / `downvotes`.
	 *
	 * @param int $id Item ID.
	 * @return array
	 */
	private function visible_counts( $id ) {
		$counts = Vote::counts( $id );
		$data   = array();

		foreach ( Vote::TYPES as $type ) {
			if ( $this->can_see_count( $type, $id ) ) {
				$data[ $type . 's' ] = $counts[ $type ];
			}
		}

		return $data;
	}

	/**
	 * Cast a vote. One vote per visitor per item, enforced here, not in the browser.
	 */
	public function vote( $request ) {
		$id   = $this->get_public_task( $request->get_param( 'id' ) )->ID;
		$type = $request->get_param( 'type' );

		if ( 'logged_in' === niroroadmap_get_setting( 'vote_who' ) && ! is_user_logged_in() ) {
			$this->response_error( array( 'message' => __( 'Please log in to vote.', 'niroroadmap' ) ), 401 );
		}

		if ( 'downvote' === $type && ! niroroadmap_get_setting( 'show_downvote' ) ) {
			$this->response_error( array( 'message' => __( 'Downvoting is turned off.', 'niroroadmap' ) ), 403 );
		}

		/**
		 * Filters how many votes one IP address may send, and over how many seconds.
		 *
		 * @param array $limit `array( votes, seconds )`. Default 30 votes per 10 minutes.
		 */
		$limit = apply_filters( 'niroroadmap_vote_rate_limit', array( 30, 10 * MINUTE_IN_SECONDS ) );

		if ( ! Rate_Limit::allow( 'vote', Voter::ip_hash(), (int) $limit[0], (int) $limit[1] ) ) {
			$this->response_error( array( 'message' => __( 'Too many votes in a short time. Please try again later.', 'niroroadmap' ) ), 429 );
		}

		$voter = Voter::identify();

		// An anonymous visitor needs a cookie to be recognised next time.
		if ( '' === $voter['hash'] ) {
			Voter::issue_cookie();
			$voter = Voter::identify();
		}

		$result = Vote::cast( $id, $type, $voter, niroroadmap_get_setting( 'allow_vote_change' ) );

		$data = array( 'vote' => $result['vote'] ) + $this->visible_counts( $id );

		// Kept for older scripts: the count of the type that was voted on (absent when hidden).
		if ( isset( $data[ $result['vote'] . 's' ] ) ) {
			$data['votes'] = $data[ $result['vote'] . 's' ];
		}

		if ( 'duplicate' === $result['status'] ) {
			$data['message'] = __( 'You have already voted on this item.', 'niroroadmap' );
			$this->response_error( $data, 409 );
		}

		$data['message'] = __( 'Vote submitted', 'niroroadmap' );
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
