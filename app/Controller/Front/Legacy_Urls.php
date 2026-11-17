<?php
namespace NiroRoadmap\Controller\Front;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Traits\Hook;

/**
 * Sends the old status, product and tag archive addresses to their current ones.
 */
class Legacy_Urls {

	use Hook;

	const SLUGS = array(
		'product-cat'   => 'niroroadmap_status',
		'product-brand' => 'niroroadmap_product',
		'product-tag'   => 'niroroadmap_tag',
	);

	/**
	 * Constructor to add all hooks.
	 */
	public function __construct() {
		$this->action( 'template_redirect', array( $this, 'redirect' ) );
	}

	/**
	 * Only for a request nothing else answered: another plugin may own these slugs.
	 */
	public function redirect() {
		if ( ! is_404() ) {
			return;
		}

		global $wp;

		$url = self::new_url( (string) $wp->request );

		if ( $url ) {
			wp_safe_redirect( $url, 301 );
			exit;
		}
	}

	/**
	 * The current archive URL for an old archive path, or '' if the path isn't one.
	 *
	 * @param string $path Request path, e.g. `product-cat/planned`.
	 * @return string
	 */
	public static function new_url( $path ) {
		if ( ! preg_match( '#^(product-cat|product-brand|product-tag)/([^/]+)/?$#', $path, $m ) ) {
			return '';
		}

		$term = get_term_by( 'slug', sanitize_title( $m[2] ), self::SLUGS[ $m[1] ] );
		$link = $term ? get_term_link( $term ) : '';

		return is_string( $link ) ? $link : '';
	}
}
