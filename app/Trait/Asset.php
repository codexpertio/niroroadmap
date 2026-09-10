<?php
namespace NiroRoadmap\Trait;

defined( 'ABSPATH' ) || exit;

/**
 * Trait Asset
 *
 * This trait provides methods for registering and enqueuing scripts and styles in the WordPress plugin.
 *
 * @package NiroRoadmap
 */
trait Asset {

	/**
	 * Registers a script with WordPress.
	 *
	 * @param string $handle    Script handle.
	 * @param string $src       Script source URL.
	 * @param array  $deps      Script dependencies.
	 * @param string $ver       Script version, defaults to the constant NIROROADMAP_VERSION.
	 * @param bool   $in_footer Whether to enqueue the script in the footer.
	 */
	public function register_script( $handle, $src, $deps = array(), $ver = null, $in_footer = true ) {
		wp_register_script( $handle, $src, $deps, $ver ?: NIROROADMAP_VERSION, $in_footer );
	}

	/**
	 * Enqueues a script in WordPress, registering it first if not already registered.
	 *
	 * @param string $handle    Script handle.
	 * @param string $src       Script source URL.
	 * @param array  $deps      Script dependencies.
	 * @param string $ver       Script version, defaults to the constant NIROROADMAP_VERSION.
	 * @param bool   $in_footer Whether to enqueue the script in the footer.
	 */
	public function enqueue_script( $handle, $src, $deps = array(), $ver = null, $in_footer = true ) {
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			$this->register_script( $handle, $src, $deps, $ver, $in_footer );
		}

		wp_enqueue_script( $handle );
	}

	/**
	 * Localizes a script in WordPress.
	 *
	 * @param string $handle Script handle.
	 * @param string $object_name Name of the JavaScript object.
	 * @param array  $l10n        Data to localize.
	 */
	public function localize_script( $handle, $object_name, $l10n ) {
		wp_localize_script( $handle, $object_name, $l10n );
	}

	/**
	 * Registers a style with WordPress.
	 *
	 * @param string $handle Style handle.
	 * @param string $src    Style source URL.
	 * @param array  $deps   Style dependencies.
	 * @param string $ver    Style version, defaults to the constant NIROROADMAP_VERSION.
	 * @param string $media  Media for which this stylesheet has been defined.
	 */
	public function register_style( $handle, $src, $deps = array(), $ver = null, $media = 'all' ) {
		wp_register_style( $handle, $src, $deps, $ver ?: NIROROADMAP_VERSION, $media );
	}

	/**
	 * Enqueues a style in WordPress, registering it first if not already registered.
	 *
	 * @param string $handle Style handle.
	 * @param string $src    Style source URL.
	 * @param array  $deps   Style dependencies.
	 * @param string $ver    Style version, defaults to the constant NIROROADMAP_VERSION.
	 * @param string $media  Media for which this stylesheet has been defined.
	 */
	public function enqueue_style( $handle, $src, $deps = array(), $ver = null, $media = 'all' ) {
		if ( ! wp_style_is( $handle, 'registered' ) ) {
			$this->register_style( $handle, $src, $deps, $ver, $media );
		}

		wp_enqueue_style( $handle );
	}
}
