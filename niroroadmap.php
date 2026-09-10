<?php
/**
 * Plugin Name: NiroRoadmap
 * Plugin URI: https://easysuite.io
 * Author: EasyCommerce
 * Author URI: https://easysuite.io/niroroadmap
 * Description: Build and share your product roadmap with a visual, drag-and-drop Kanban board.
 * Version: 0.9.1
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Text Domain: niroroadmap
 * Domain Path: /languages
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * NiroRoadmap is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * NiroRoadmap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

namespace NiroRoadmap;

defined( 'ABSPATH' ) || exit;

define( 'NIROROADMAP_FILE', __FILE__ );
define( 'NIROROADMAP_VERSION', '0.9.1' );
define( 'NIROROADMAP_PLUGIN_DIR', plugin_dir_path( NIROROADMAP_FILE ) );
define( 'NIROROADMAP_PLUGIN_URL', plugin_dir_url( NIROROADMAP_FILE ) );
define( 'NIROROADMAP_ASSETS_URL', NIROROADMAP_PLUGIN_URL . 'assets/' );

require_once 'vendor/autoload.php';

/**
 * Register the activation hook.
 * This hook is triggered when the plugin is activated.
 * It installs necessary database tables, sets initial seeds,
 * and checks database versions.
 */
register_activation_hook( NIROROADMAP_FILE, __NAMESPACE__ . '\\niroroadmap_install' );
function niroroadmap_install() {
	Bootstrap\Installer::install();
}

/**
 * Register the deactivation hook.
 * This hook is triggered when the plugin is activated.
 * It uninstalls necessary database tables, sets initial seeds,
 * and checks database versions.
 */
register_deactivation_hook( NIROROADMAP_FILE, __NAMESPACE__ . '\\niroroadmap_uninstall' );
function niroroadmap_uninstall() {
	Bootstrap\Uninstaller::uninstall();
}

/**
 * Add action for plugins_loaded to activate the plugin.
 * This action is triggered when all active plugins are fully loaded.
 * It sets up cron jobs, registers custom user roles, and performs other
 * necessary activation tasks.
 */
add_action( 'plugins_loaded', __NAMESPACE__ . '\\niroroadmap_activate' );
function niroroadmap_activate() {
	Bootstrap\Activator::activate();
}

/**
 * Add action for plugins_loaded to initialize the plugin.
 * This action is triggered when all active plugins are fully loaded.
 * It sets the plugin's runtime environment and initializes hooks.
 */
add_action( 'plugins_loaded', __NAMESPACE__ . '\\niroroadmap_initialize' );
function niroroadmap_initialize() {
	Bootstrap\Initializer::initialize();
}