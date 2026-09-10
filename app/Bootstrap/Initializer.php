<?php
namespace NiroRoadmap\Bootstrap;

defined( 'ABSPATH' ) || exit;

class Initializer {

	/**
	 * Initialize the plugin's components.
	 */
	public static function initialize() {
		$initializer = new self();

		$initializer->load_admin_controllers();
		$initializer->load_public_controllers();
		$initializer->load_common_controllers();
	}

	/**
	 * Initialize controllers for wp-admin.
	 */
	private function load_admin_controllers() {
		if ( is_admin() ) {
			$controller_dir = NIROROADMAP_PLUGIN_DIR . 'app/Controller/Admin/';

			foreach ( glob( $controller_dir . '*.php' ) as $file ) {
				$class_name = basename( $file, '.php' );
				$controller = "\\NiroRoadmap\\Controller\\Admin\\{$class_name}";

				if ( class_exists( $controller ) ) {
					new $controller();
				}
			}
		}
	}

	/**
	 * Initialize controllers for public-facing parts of the site.
	 */
	private function load_public_controllers() {
		if ( ! is_admin() ) {
			$controller_dir = NIROROADMAP_PLUGIN_DIR . 'app/Controller/Public/';

			foreach ( glob( $controller_dir . '*.php' ) as $file ) {
				$class_name = basename( $file, '.php' );
				$controller = "\\NiroRoadmap\\Controller\\Public\\{$class_name}";

				if ( class_exists( $controller ) ) {
					new $controller();
				}
			}
		}
	}

	/**
	 * Initialize controllers that operate on both admin and public.
	 */
	private function load_common_controllers() {
		$controller_dir = NIROROADMAP_PLUGIN_DIR . 'app/Controller/Common/';

		foreach ( glob( $controller_dir . '*.php' ) as $file ) {
			$class_name = basename( $file, '.php' );
			$controller = "\\NiroRoadmap\\Controller\\Common\\{$class_name}";

			if ( class_exists( $controller ) ) {
				new $controller();
			}
		}
	}
}
