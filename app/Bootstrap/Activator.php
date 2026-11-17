<?php
namespace NiroRoadmap\Bootstrap;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Traits\Hook;

class Activator {

	use Hook;

	/**
	 * Raise when a rewrite slug changes, so every site flushes its rules once.
	 */
	const REWRITE_VERSION = '2';

	/**
	 * Static method for plugin activation tasks.
	 */
	public static function activate() {
		$activator = new self();

		$activator->set_cron();
		$activator->register_post_types();
		$activator->register_taxonomies();
		$activator->seed_statuses();
		$activator->flush_rewrites();
		$activator->migrate();

		// Sites that update the plugin don't go through activation, so check the schema on load too.
		Installer::maybe_upgrade();

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

	public function flush_rewrites() {
		$this->action( 'init', array( self::class, 'maybe_flush_rewrites' ), 99 );
	}

	public function migrate() {
		$this->action( 'init', array( Migrator::class, 'maybe_run' ), 100 );
	}

	/**
	 * Flush the rewrite rules once per REWRITE_VERSION, after the post type and taxonomies are registered.
	 */
	public static function maybe_flush_rewrites() {
		if ( self::REWRITE_VERSION === get_option( 'niroroadmap_rewrite_version' ) ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'niroroadmap_rewrite_version', self::REWRITE_VERSION );
	}
}
