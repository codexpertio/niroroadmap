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
