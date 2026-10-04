<?php
namespace NiroRoadmap\Model;

defined( 'ABSPATH' ) || exit;

/**
 * The search / sort / filter toolbar above a board: what it offers, and its assets.
 *
 * Everything is decided on the server, so a visitor can't be offered a control that would expose
 * something a setting hides (e.g. sorting by votes when vote counts are turned off).
 */
class Toolbar {

	const SORTS = array( 'manual', 'votes', 'newest', 'oldest', 'commented' );

	const FILTERS = array( 'search', 'tag', 'product' );

	/**
	 * Sort modes with their labels, in menu order.
	 *
	 * @return array<string,string>
	 */
	public static function sort_labels() {
		return array(
			'manual'    => __( 'Manual order', 'niroroadmap' ),
			'votes'     => __( 'Most votes', 'niroroadmap' ),
			'newest'    => __( 'Newest', 'niroroadmap' ),
			'oldest'    => __( 'Oldest', 'niroroadmap' ),
			'commented' => __( 'Most commented', 'niroroadmap' ),
		);
	}

	/**
	 * Sort modes this board may offer. Modes built on a number a setting hides are left out.
	 *
	 * @return array<string,string>
	 */
	public static function available_sorts() {
		$sorts = self::sort_labels();

		if ( ! niroroadmap_get_setting( 'show_vote_counts' ) ) {
			unset( $sorts['votes'] );
		}

		if ( ! Comment::enabled() ) {
			unset( $sorts['commented'] );
		}

		return $sorts;
	}

	/**
	 * Work out what a board gets.
	 *
	 * @param array $args `toolbar` (`yes` / `no`), `sort`, `filters` (comma list, or `none`). Empty
	 *                    or missing values follow Settings -> Toolbar.
	 * @return array {
	 *     @type bool   $enabled Whether the board has a toolbar.
	 *     @type string $sort    Initial sort mode.
	 *     @type array  $filters `search` / `tag` / `product` => bool.
	 *     @type array  $sorts   Sort modes offered, slug => label.
	 * }
	 */
	public static function config( $args = array() ) {
		$toolbar = strtolower( (string) ( $args['toolbar'] ?? '' ) );
		$enabled = in_array( $toolbar, array( 'yes', 'no' ), true ) ? 'yes' === $toolbar : (bool) niroroadmap_get_setting( 'toolbar_enabled' );
		$sorts   = self::available_sorts();

		// An initial sort the board can't offer falls back, rather than showing a mode with no control.
		$sort = strtolower( (string) ( $args['sort'] ?? '' ) );
		if ( ! isset( $sorts[ $sort ] ) ) {
			$sort = (string) niroroadmap_get_setting( 'toolbar_sort' );
		}
		if ( ! isset( $sorts[ $sort ] ) ) {
			$sort = 'manual';
		}

		$filters = array(
			'search'  => (bool) niroroadmap_get_setting( 'toolbar_filter_search' ),
			'tag'     => (bool) niroroadmap_get_setting( 'toolbar_filter_tag' ),
			'product' => (bool) niroroadmap_get_setting( 'toolbar_filter_product' ),
		);

		$wanted = strtolower( trim( (string) ( $args['filters'] ?? '' ) ) );
		if ( '' !== $wanted ) {
			$tokens  = array_map( 'trim', explode( ',', $wanted ) );
			$filters = array_fill_keys( self::FILTERS, false );

			foreach ( $tokens as $token ) {
				if ( isset( $filters[ $token ] ) ) {
					$filters[ $token ] = true;
				}
			}
		}

		// Tags hidden on the board can't be searched or filtered by either.
		if ( ! niroroadmap_get_setting( 'show_tags' ) ) {
			$filters['tag'] = false;
		}

		return array(
			'enabled' => $enabled,
			'sort'    => $sort,
			'filters' => $filters,
			'sorts'   => $sorts,
		);
	}

	/**
	 * Load the toolbar's script and stylesheet. Called while a board renders, so a page without a
	 * toolbar never loads them.
	 */
	public static function enqueue() {
		if ( ! wp_script_is( 'niroroadmap-toolbar', 'registered' ) ) {
			wp_register_style( 'niroroadmap-toolbar', NIROROADMAP_ASSETS_URL . 'public/css/toolbar.css', array( 'niroroadmap-public' ), NIROROADMAP_VERSION );
			wp_register_script( 'niroroadmap-toolbar', NIROROADMAP_ASSETS_URL . 'public/js/toolbar.js', array( 'jquery', 'niroroadmap-public' ), NIROROADMAP_VERSION, true );

			wp_localize_script(
				'niroroadmap-toolbar',
				'NIROROADMAP_TOOLBAR',
				array(
					'i18n' => array(
						/* translators: 1: number of items shown, 2: total number of items. */
						'showing'  => __( 'Showing %1$s of %2$s', 'niroroadmap' ),
						'no_match' => __( 'No matching items', 'niroroadmap' ),
					),
				)
			);
		}

		wp_enqueue_style( 'niroroadmap-toolbar' );
		wp_enqueue_script( 'niroroadmap-toolbar' );
	}
}
