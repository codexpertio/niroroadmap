<?php
namespace NiroRoadmap\Controller\Common;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Model\Fields as Item_Fields;
use NiroRoadmap\Trait\Hook;

class Fields {

	use Hook;

	/**
	 * Constructor to add all hooks.
	 */
	public function __construct() {
		$this->action( 'init', array( $this, 'register_meta' ) );
	}

	public function register_meta() {
		Item_Fields::register();
	}
}
