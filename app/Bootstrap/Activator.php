<?php
namespace NiroRoadmap\Bootstrap;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Trait\Hook;

class Activator {

	use Hook;

	/**
	 * Static method for plugin activation tasks.
	 */
	public static function activate() {
		$activator = new self();

		$activator->set_cron();
		$activator->register_post_types();
		$activator->register_taxonomies();
		$activator->seed_statuses();

		// Set a flag that indicates the plugin has been activated
		update_option( 'niroroadmap_activated', true );
	}

	public function set_cron() {
		// code...
	}

	public function register_post_types() {
		$this->action( 'init', array( new Activator\Post_Type(), 'register' ) );
	}

	public function register_taxonomies() {
		$this->action( 'init', array( new Activator\Taxonomy(), 'register' ) );
	}

	public function seed_statuses() {
		$this->action( 'init', array( Installer::class, 'seed_statuses' ), 20 );
	}
}
