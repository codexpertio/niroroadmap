<?php
namespace NiroRoadmap\Controller\Public;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Model\Roadmap;
use NiroRoadmap\Trait\Hook;

class Shortcode {

	use Hook;

	/**
	 * Constructor to add all hooks.
	 */
	public function __construct() {
		$this->shortcode( 'roadmap', array( $this, 'callback_roadmap' ) );
	}

	public function callback_roadmap( $atts ) {
		$atts = shortcode_atts( array( 'product' => null ), $atts, 'roadmap' );
		$product = $atts[ 'product' ] ?? null;
		
		return Roadmap::get_roadmap( $product );
	}
}
