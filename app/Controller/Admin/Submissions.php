<?php
namespace NiroRoadmap\Controller\Admin;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Model\Submission;
use NiroRoadmap\Trait\Hook;

/**
 * Items screen helpers for ideas visitors submitted: who sent it, and a one-click Approve.
 */
class Submissions {

	use Hook;

	/**
	 * Constructor to add all hooks.
	 */
	public function __construct() {
		$this->filter( 'manage_niroroadmap_item_posts_columns', array( $this, 'add_column' ) );
		$this->action( 'manage_niroroadmap_item_posts_custom_column', array( $this, 'show_column' ), 10, 2 );
		$this->filter( 'post_row_actions', array( $this, 'add_approve_action' ), 10, 2 );
		$this->action( 'admin_post_niroroadmap_approve_submission', array( $this, 'approve' ) );
		$this->action( 'admin_notices', array( $this, 'show_notice' ) );
	}

	public function add_column( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new['niroroadmap_submitter'] = __( 'Submitted by', 'niroroadmap' );
			}
		}

		return $new;
	}

	public function show_column( $column, $post_id ) {
		if ( 'niroroadmap_submitter' !== $column ) {
			return;
		}

		if ( ! get_post_meta( $post_id, Submission::META_FLAG, true ) ) {
			echo '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Not a visitor submission', 'niroroadmap' ) . '</span>';
			return;
		}

		$name  = get_post_meta( $post_id, Submission::META_NAME, true );
		$email = get_post_meta( $post_id, Submission::META_EMAIL, true );

		if ( ! $name && ! $email ) {
			esc_html_e( 'Anonymous visitor', 'niroroadmap' );
			return;
		}

		echo esc_html( $name ? $name : $email );

		if ( $name && $email ) {
			echo '<br><a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>';
		}
	}

	/**
	 * Add "Approve" to pending visitor submissions.
	 */
	public function add_approve_action( $actions, $post ) {
		if ( 'niroroadmap_item' !== $post->post_type || 'pending' !== $post->post_status || ! get_post_meta( $post->ID, Submission::META_FLAG, true ) || ! current_user_can( 'publish_post', $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'niroroadmap_approve_submission',
					'post'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			'niroroadmap_approve_' . $post->ID
		);

		return array( 'niroroadmap_approve' => sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Approve', 'niroroadmap' ) ) ) + $actions;
	}

	/**
	 * Publish a pending submission.
	 */
	public function approve() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		check_admin_referer( 'niroroadmap_approve_' . $post_id );

		$post = get_post( $post_id );

		if ( ! $post || 'niroroadmap_item' !== $post->post_type || ! current_user_can( 'publish_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'niroroadmap' ), '', array( 'response' => 403 ) );
		}

		if ( 'pending' === $post->post_status ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				)
			);
		}

		wp_safe_redirect( add_query_arg( 'niroroadmap_approved', '1', admin_url( 'edit.php?post_type=niroroadmap_item' ) ) );
		exit;
	}

	public function show_notice() {
		if ( isset( $_GET['niroroadmap_approved'] ) && 'edit-niroroadmap_item' === ( get_current_screen()->id ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Idea approved. It is now on the board.', 'niroroadmap' ) . '</p></div>';
		}
	}
}
