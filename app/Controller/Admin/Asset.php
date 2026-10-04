<?php
namespace NiroRoadmap\Controller\Admin;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Trait\Hook;
use NiroRoadmap\Trait\Asset as Asset_Trait;
use NiroRoadmap\Helper\Utility;

class Asset {

	use Hook;
	use Asset_Trait;

	/**
	 * Constructor to add all hooks.
	 */
	public function __construct() {
		$this->action( 'admin_enqueue_scripts', array( $this, 'add_assets' ) );
	}

	public function add_assets() {
		global $current_screen;

		if ( 'niroroadmap_status' === $current_screen->taxonomy ) {

			$this->enqueue_style(
				'niroroadmap-admin',
				NIROROADMAP_ASSETS_URL . 'admin/css/style.css'
			);
		}

		if ( 'edit-tags' === $current_screen->base && 'niroroadmap_status' === $current_screen->taxonomy ) {

			$this->enqueue_script(
				'niroroadmap-sorter',
				NIROROADMAP_ASSETS_URL . 'admin/js/sorter.js',
				array( 'jquery', 'jquery-ui-sortable', 'niroroadmap' )
			);
		}
	}
}
