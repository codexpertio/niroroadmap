<?php
namespace NiroRoadmap\API;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Helper\Rate_Limit;
use NiroRoadmap\Helper\Voter;
use NiroRoadmap\Model\Comment as Comment_Model;
use NiroRoadmap\Model\Roadmap;
use NiroRoadmap\Trait\Rest;

class Comment {

	use Rest;

	/**
	 * The item behind a request, or a 404. Comments turned off count as "not found" too.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_Post
	 */
	private function get_item( $request ) {
		$item = Roadmap::get_public_item( $request->get_param( 'id' ) );

		if ( ! $item || ! Comment_Model::enabled() ) {
			$this->response_error( array( 'message' => __( 'Comments are not available.', 'niroroadmap' ) ), 404 );
		}

		return $item;
	}

	/**
	 * GET /tasks/{id}/comments
	 */
	public function get( $request ) {
		$item = $this->get_item( $request );

		// Includes the viewer's own pending comments and their permissions: never cache it.
		nocache_headers();

		$this->response_success(
			Comment_Model::page( $item, (int) $request->get_param( 'page' ), (int) $request->get_param( 'per_page' ) ?: 20 )
			+ array( 'state' => Comment_Model::state( $item ) )
		);
	}

	/**
	 * POST /tasks/{id}/comments
	 */
	public function create( $request ) {
		$item = $this->get_item( $request );

		// A bot fills the hidden field. Answer as if it worked so it learns nothing, and store nothing.
		if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
			$this->response_success(
				array(
					'message' => __( 'Thanks! Your comment is awaiting moderation.', 'niroroadmap' ),
					'pending' => true,
				)
			);
		}

		/**
		 * Filters how many comments one IP address may send, and over how many seconds.
		 * Moderators are never limited.
		 *
		 * @param array $limit `array( comments, seconds )`. Default 5 per 10 minutes.
		 */
		$limit = apply_filters( 'niroroadmap_comment_rate_limit', array( 5, 10 * MINUTE_IN_SECONDS ) );

		if ( ! current_user_can( 'moderate_comments' ) && ! Rate_Limit::allow( 'comment', Voter::ip_hash(), (int) $limit[0], (int) $limit[1] ) ) {
			$this->response_error( array( 'message' => __( 'You are commenting too fast. Please try again in a few minutes.', 'niroroadmap' ) ), 429 );
		}

		$result = Comment_Model::create(
			$item,
			array(
				'content' => $request->get_param( 'content' ),
				'parent'  => $request->get_param( 'parent' ),
				'name'    => $request->get_param( 'name' ),
				'email'   => $request->get_param( 'email' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) && ! empty( $data['status'] ) ? (int) $data['status'] : ( is_int( $data ) && $data >= 400 ? $data : 400 );

			// WordPress error messages can carry markup such as "<strong>Error:</strong>".
			$this->response_error( array( 'message' => wp_strip_all_tags( $result->get_error_message() ) ), $status );
		}

		$result['message'] = $result['pending']
			? __( 'Thanks! Your comment is awaiting moderation.', 'niroroadmap' )
			: __( 'Comment posted.', 'niroroadmap' );

		$this->response_success( $result );
	}
}
