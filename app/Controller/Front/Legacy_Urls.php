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
	 * @param string $text    Text, HTML or a single URL.
	 * @param int    $skipped Counts links left alone because a shop term owns the same address.
	 * @return string
	 */
	public static function replace( $text, &$skipped = 0 ) {
		$home = preg_quote( untrailingslashit( preg_replace( '#^https?:#', '', home_url() ) ), '#' );

		return preg_replace_callback(
			'#(?<![\w/.-])((?:https?:)?' . $home . ')?/((?:product-cat|product-brand|product-tag)/[^/\s"\'<>?\#]+(?:/(?:feed(?:/[a-z0-9_-]+)?|page/\d+))?/?)(?=[\s"\'<>?\#]|$)#i',
			static function ( $m ) use ( &$skipped ) {
				if ( self::is_shop_tag( $m[2] ) ) {
					++$skipped;
					return $m[0];
				}

				$url = self::new_url( $m[2] );

				return $url ? $url : $m[0];
			},
			$text
		);
	}

	/**
	 * Whether an old `product-tag/<slug>` path may belong to another taxonomy that now owns that base.
	 *
	 * @param string $path Old path.
	 * @return bool
	 */
	public static function is_shop_tag( $path ) {
		if ( ! preg_match( '#^product-tag/([^/]+)#', $path, $m ) ) {
			return false;
		}

		foreach ( get_taxonomies( array(), 'objects' ) as $name => $taxonomy ) {
			if ( 'niroroadmap_tag' !== $name && ! empty( $taxonomy->rewrite['slug'] ) && 'product-tag' === $taxonomy->rewrite['slug'] && get_term_by( 'slug', sanitize_title( $m[1] ), $name ) ) {
				return true;
			}
		}

		return false;
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
