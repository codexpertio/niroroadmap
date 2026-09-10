<?php
namespace NiroRoadmap\API;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Trait\Rest;
use NiroRoadmap\Trait\Cleaner;

class Stage {

	use Rest;
	use Cleaner;

	/**
	 * Sort stage orders
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function order( $request ) {
		$order = $request->get_param( 'order' );

		foreach ( $order as $position => $term_id ) {
			$term_id = (int) str_replace( 'tag-', '', $term_id );

			update_term_meta( $term_id, 'menu_order', $position );
		}

		$this->response_success( array( 'message' => __( 'Stage order changed', 'niroroadmap' ) ) );
	}
}
