<?php
namespace NiroRoadmap\Helper\Field;

use NiroRoadmap\Abstract\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Date Field Class
 */
class Date extends Text {

	public function __construct( $config = array() ) {
		parent::__construct( $config );
		$this->set_type( 'date' );
	}
}
