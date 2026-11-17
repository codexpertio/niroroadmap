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
		// Core answers any unknown `<path>/feed/` with an empty comments feed instead of a 404.
		if ( ! is_404() && ! ( is_feed() && ! get_queried_object() ) ) {
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
	 * @param string $path Request path, e.g. `product-cat/planned` or `product-cat/planned/feed`.
	 * @return string
	 */
	public static function new_url( $path ) {
		if ( ! preg_match( '#^(product-cat|product-brand|product-tag)/([^/]+)(?:/(feed(?:/([a-z0-9_-]+))?|page/(\d+)))?/?$#', $path, $m ) ) {
			return '';
		}

		$taxonomy = self::SLUGS[ $m[1] ];
		$term     = get_term_by( 'slug', sanitize_title( $m[2] ), $taxonomy );
		$link     = $term ? get_term_link( $term ) : '';

		if ( ! is_string( $link ) || '' === $link ) {
			return '';
		}

		if ( ! empty( $m[5] ) ) {
			return trailingslashit( $link ) . user_trailingslashit( 'page/' . (int) $m[5], 'paged' );
		}

		if ( ! empty( $m[3] ) ) {
			return get_term_feed_link( $term->term_id, $taxonomy, empty( $m[4] ) ? '' : $m[4] );
		}

		return $link;
	}

	/**
	 * Swap every old roadmap archive URL on this site in a piece of text for its current URL.
	 *
	 * @param string $text Text, HTML or a single URL.
	 * @return string
	 */
	public static function replace( $text ) {
		$home = preg_quote( untrailingslashit( preg_replace( '#^https?:#', '', home_url() ) ), '#' );

		return preg_replace_callback(
			'#(?<![\w/.-])((?:https?:)?' . $home . ')?/((?:product-cat|product-brand|product-tag)/[^/\s"\'<>?\#]+(?:/(?:feed(?:/[a-z0-9_-]+)?|page/\d+))?/?)(?=[\s"\'<>?\#]|$)#i',
			static function ( $m ) {
				$url = self::new_url( $m[2] );

				return $url ? $url : $m[0];
			},
			$text
		);
	}

	/**
	 * Old archive base => current base, for caches and SEO plugins that need to purge.
	 *
	 * @return array
	 */
	public static function bases() {
		$bases = array();

		foreach ( self::SLUGS as $old => $taxonomy ) {
			$object        = get_taxonomy( $taxonomy );
			$bases[ $old ] = $object && ! empty( $object->rewrite['slug'] ) ? $object->rewrite['slug'] : '';
		}

		return $bases;
	}
}
