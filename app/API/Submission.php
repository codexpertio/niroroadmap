<?php
namespace NiroRoadmap\API;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Helper\Rate_Limit;
use NiroRoadmap\Helper\Voter;
use NiroRoadmap\Model\Submission as Submission_Model;
use NiroRoadmap\Trait\Rest;

class Submission {

	use Rest;

	/**
	 * POST /tasks/submit
	 *
	 * Needs no nonce, so it works on cached pages. The defences are a honeypot, a minimum
	 * fill-in time, a per-IP rate limit, an optional login requirement and a hook for a CAPTCHA.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function create( $request ) {
		if ( ! Submission_Model::api_available() ) {
			$this->response_error( array( 'message' => __( 'Suggestions are not available.', 'niroroadmap' ) ), 404 );
		}

		$thanks = __( 'Thanks! We’ll review it.', 'niroroadmap' );

		// A bot fills the hidden field. Answer as if it worked, so it learns nothing, and store nothing.
		if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
			$this->response_success( array( 'message' => $thanks ) );
		}

		if ( niroroadmap_get_setting( 'submissions_login' ) && ! is_user_logged_in() ) {
			$this->response_error( array( 'message' => __( 'Please log in to suggest an idea.', 'niroroadmap' ) ), 401 );
		}

		// The form reports how long it was open. A script that posts straight to the API sends nothing.
		if ( (int) $request->get_param( 'elapsed' ) < Submission_Model::min_time() ) {
			$this->response_error( array( 'message' => __( 'That was quick! Please check your idea and send it again.', 'niroroadmap' ) ), 400 );
		}

		/**
		 * Filters how many ideas one IP address may send, and over how many seconds.
		 * People who can edit others' posts are never limited.
		 *
		 * @param array $limit `array( ideas, seconds )`. Default 3 per hour.
		 */
		$limit = apply_filters( 'niroroadmap_submission_rate_limit', array( 3, HOUR_IN_SECONDS ) );

		if ( ! current_user_can( 'edit_others_posts' ) && ! Rate_Limit::allow( 'submission', Voter::ip_hash(), (int) $limit[0], (int) $limit[1] ) ) {
			$this->response_error( array( 'message' => __( 'You have sent several ideas recently. Please try again later.', 'niroroadmap' ) ), 429 );
		}

		/**
		 * Lets a CAPTCHA (reCAPTCHA, Turnstile, ...) veto a submission. Return a WP_Error to refuse it.
		 *
		 * @param true|\WP_Error   $allowed Default true.
		 * @param \WP_REST_Request $request The request, with the CAPTCHA token if the form sent one.
		 */
		$allowed = apply_filters( 'niroroadmap_submission_verify', true, $request );

		if ( is_wp_error( $allowed ) ) {
			if ( ! current_user_can( 'edit_others_posts' ) ) {
				Rate_Limit::release( 'submission', Voter::ip_hash(), (int) $limit[1] );
			}

			$this->response_error( array( 'message' => wp_strip_all_tags( $allowed->get_error_message() ) ), 403 );
		}

		$result = Submission_Model::create(
			array(
				'title'       => $request->get_param( 'title' ),
				'description' => $request->get_param( 'description' ),
				'product'     => $request->get_param( 'product' ),
				'name'        => $request->get_param( 'name' ),
				'email'       => $request->get_param( 'email' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) && ! empty( $data['status'] ) ? (int) $data['status'] : 400;

			// Nothing was stored, so a typo shouldn't cost the visitor one of their ideas.
			if ( $status < 500 && ! current_user_can( 'edit_others_posts' ) ) {
				Rate_Limit::release( 'submission', Voter::ip_hash(), (int) $limit[1] );
			}

			$this->response_error( array( 'message' => wp_strip_all_tags( $result->get_error_message() ) ), $status );
		}

		// No ID in the response: the item isn't public, so there's nothing for a visitor to do with it.
		$this->response_success( array( 'message' => $thanks ) );
	}

	/**
	 * GET /tasks/search?q=...
	 *
	 * Published items that look like what the visitor is typing.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function search( $request ) {
		if ( ! Submission_Model::api_available() ) {
			$this->response_error( array( 'message' => __( 'Suggestions are not available.', 'niroroadmap' ) ), 404 );
		}

		// Typing fires a request per pause; keep a script from using this as a free search API.
		if ( ! Rate_Limit::allow( 'submission_search', Voter::ip_hash(), 30, MINUTE_IN_SECONDS ) ) {
			$this->response_error( array( 'message' => __( 'Too many searches. Please slow down.', 'niroroadmap' ) ), 429 );
		}

		$this->response_success( array( 'items' => Submission_Model::similar( (string) $request->get_param( 'q' ) ) ) );
	}
}
