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
		$this->shortcode( 'niroroadmap', array( $this, 'callback_roadmap' ) );
		$this->shortcode( 'roadmap', array( $this, 'callback_roadmap' ) ); // back compat
	}

	public function callback_roadmap( $atts ) {
		$atts = shortcode_atts(
			array(
				'product'     => null,
				'submissions' => '', // yes | no. Anything else follows Settings -> Submissions.
				'toolbar'     => '', // yes | no. Anything else follows Settings -> Toolbar.
				'sort'        => '', // manual | votes | newest | oldest | commented.
				'filters'     => '', // Comma list of search, tag, product; "none" for no filters.
				'view'        => '', // board | list | timeline. Anything else follows Settings -> Views.
				'switcher'    => '', // yes | no. Anything else follows Settings -> Views.
				'group'       => '', // quarter | month | nownext: how the timeline groups items.
			),
			$atts,
			'roadmap'
		);
		$product = $atts[ 'product' ] ?? null;

		return Roadmap::get_roadmap(
			$product,
			array(
				'submissions' => $atts['submissions'],
				'toolbar'     => $atts['toolbar'],
				'sort'        => $atts['sort'],
				'filters'     => $atts['filters'],
				'view'        => $atts['view'],
				'switcher'    => $atts['switcher'],
				'group'       => $atts['group'],
			)
		);
	}
}
