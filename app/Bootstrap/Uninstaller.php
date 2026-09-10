<?php
namespace NiroRoadmap\Bootstrap;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Model\Database;

class Uninstaller {

	/**
	 * Run installation routines.
	 */
	public static function uninstall() {
		$uninstaller = new self();

		$uninstaller->remove_db_version();
	}

	/**
	 * Remove the database version from the options table.
	 */
	protected function remove_db_version() {
		delete_option( 'niroroadmap_db_version' );
	}
}
