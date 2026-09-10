<?php
namespace NiroRoadmap\Helper\Field;

use NiroRoadmap\Abstract\Field;

defined( 'ABSPATH' ) || exit;

/**
 * URL Field Class
 */
class URL extends Text {

	public function __construct( $config = array() ) {
		parent::__construct( $config );
		$this->set_type( 'url' );
	}
}
