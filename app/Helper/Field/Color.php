<?php
namespace NiroRoadmap\Helper\Field;

use NiroRoadmap\Abstract\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Color Field Class
 */
class Color extends Text {

	public function __construct( $config = array() ) {
		parent::__construct( $config );
		$this->set_type( 'color' );
	}
}
