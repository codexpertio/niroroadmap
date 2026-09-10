<?php
namespace NiroRoadmap\Controller\Admin;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Trait\Hook;
use NiroRoadmap\Trait\Asset;
use NiroRoadmap\Helper\Utility;

class Menu {

	use Hook;
	use Asset;

	/**
	 * Constructor to add all hooks.
	 */
	public function __construct() {
		$this->action( 'admin_enqueue_scripts', array( $this, 'add_assets' ) );
	}

	public function add_assets() {
		global $current_screen;

		if ( strpos( $current_screen->base, 'niroroadmap' ) !== false ) {

			$this->enqueue_script(
				'niroroadmap_main-menu',
				NIROROADMAP_PLUGIN_URL . 'spa/build/admin.bundle.js',
				array( 'wp-element', 'niroroadmap_common' )
			);
		}

		if ( strpos( $current_screen->base, 'niroroadmap' ) !== false ) {

			$this->enqueue_style(
				'niroroadmap_settings',
				NIROROADMAP_ASSETS_URL . 'admin/css/settings.css'
			);

			$this->enqueue_script(
				'niroroadmap_settings',
				NIROROADMAP_ASSETS_URL . 'admin/js/settings.js'
			);
		}

		if ( true ) {

			$this->enqueue_style(
				'niroroadmap',
				NIROROADMAP_ASSETS_URL . 'admin/css/style.css'
			);

			wp_enqueue_script( 'jquery-ui-sortable' );

			$this->enqueue_script(
				'niroroadmap-sorter',
				NIROROADMAP_ASSETS_URL . 'admin/js/sorter.js'
			);
		}
	}
}
