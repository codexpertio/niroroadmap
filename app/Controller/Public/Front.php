<?php
namespace NiroRoadmap\Controller\Public;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Trait\Hook;
use NiroRoadmap\Trait\Asset;
use NiroRoadmap\Helper\Settings;

class Front {

	use Hook;
	use Asset;

	/**
	 * Constructor to add all hooks.
	 */
	public function __construct() {
		$this->filter( 'body_class', array( $this, 'add_body_class' ) );
		$this->action( 'wp_enqueue_scripts', array( $this, 'add_assets' ) );
		$this->filter( 'niroroadmap-localized_vars', array( $this, 'add_localized_vars' ) );
	}

	/**
	 * Pass the front-end script what it can't get from the markup.
	 *
	 * Nothing here depends on the current user, so cached pages stay correct. What a visitor may
	 * see or do is decided by the server (markup and REST responses), not by these values.
	 */
	public function add_localized_vars( $vars ) {
		$vars['settings'] = array(
			'allow_vote_change' => (bool) niroroadmap_get_setting( 'allow_vote_change' ),
			'vote_failed'       => __( 'Your vote could not be saved.', 'niroroadmap' ),
			'login_url'         => wp_login_url(),
			'comments_newest'   => 'newest' === niroroadmap_get_setting( 'comments_order' ),
			'item'              => array(
				'target'   => __( 'Target', 'niroroadmap' ),
				'released' => __( 'Released', 'niroroadmap' ),
				'version'  => __( 'Version', 'niroroadmap' ),
				'link'     => __( 'More info', 'niroroadmap' ),
			),
			'suggest'           => array(
				'need_title'    => __( 'Please give your idea a title (at least 3 characters).', 'niroroadmap' ),
				'need_identity' => __( 'Please enter your name and email.', 'niroroadmap' ),
				'sending'       => __( 'Sending…', 'niroroadmap' ),
				'send'          => __( 'Send idea', 'niroroadmap' ),
				'failed'        => __( 'Your idea could not be sent. Please try again.', 'niroroadmap' ),
			),
			'comments'          => array(
				'loading'     => __( 'Loading comments…', 'niroroadmap' ),
				'load_failed' => __( 'Could not load comments.', 'niroroadmap' ),
				'none'        => __( 'No comments yet. Be the first to share your thoughts.', 'niroroadmap' ),
				'closed'      => __( 'Comments are closed for this item.', 'niroroadmap' ),
				'login'       => __( 'Log in to comment.', 'niroroadmap' ),
				'team'        => __( 'Team', 'niroroadmap' ),
				'awaiting'    => __( 'Awaiting moderation', 'niroroadmap' ),
				'reply'       => __( 'Reply', 'niroroadmap' ),
				/* translators: %s: name of the person being replied to. */
				'replying_to' => __( 'Replying to %s', 'niroroadmap' ),
				'posted'      => __( 'Comment posted.', 'niroroadmap' ),
				'failed'      => __( 'Your comment could not be posted.', 'niroroadmap' ),
				'sending'     => __( 'Posting…', 'niroroadmap' ),
				'submit'      => __( 'Post comment', 'niroroadmap' ),
				'empty'       => __( 'Please write a comment.', 'niroroadmap' ),
				'need_name'   => __( 'Please enter your name and email.', 'niroroadmap' ),
			),
		);

		return $vars;
	}

	public function add_body_class( $classes ) {
		if ( current_user_can( 'edit_pages' ) ) {
			$classes[] = 'task-editor';
		}

		return $classes;
	}

	public function add_assets() {

		$this->enqueue_style(
			'niroroadmap-public',
			NIROROADMAP_ASSETS_URL . 'public/css/style.css'
		);

		wp_add_inline_style( 'niroroadmap-public', Settings::inline_css() );

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-sortable' );

		$this->enqueue_script(
			'niroroadmap-public',
			NIROROADMAP_ASSETS_URL . 'public/js/script.js'
		);
	}
}
