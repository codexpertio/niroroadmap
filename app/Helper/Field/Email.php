<?php
namespace NiroRoadmap\Helper\Field;

use NiroRoadmap\Abstract\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Email Field Class
 */
class Email extends Text {

	public function __construct( $config = array() ) {
		parent::__construct( $config );
		$this->set_type( 'email' );
	}
}
