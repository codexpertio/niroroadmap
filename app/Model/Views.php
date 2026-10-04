<?php
namespace NiroRoadmap\Model;

defined( 'ABSPATH' ) || exit;

/**
 * The ways a board can be shown (Board, List, Timeline), and the grouping of the timeline.
 *
 * Like the toolbar, what a board offers is decided on the server: the timeline is built on item
 * targets, so it is only offered when Settings -> General shows targets publicly. A visitor can't
 * reach a view that would expose something a setting hides.
 */
class Views {

	const VIEWS = array( 'board', 'list', 'timeline' );

	const GROUPS = array( 'quarter', 'month', 'nownext' );

	/**
	 * Views with their labels, in switcher order.
	 *
	 * @return array<string,string>
	 */
	public static function view_labels() {
		return array(
			'board'    => __( 'Board', 'niroroadmap' ),
			'list'     => __( 'List', 'niroroadmap' ),
			'timeline' => __( 'Timeline', 'niroroadmap' ),
		);
	}

	/**
	 * How the timeline groups its items.
	 *
	 * @return array<string,string>
	 */
	public static function group_labels() {
		return array(
			'quarter' => __( 'By quarter', 'niroroadmap' ),
			'month'   => __( 'By month', 'niroroadmap' ),
			'nownext' => __( 'Now / Next / Later', 'niroroadmap' ),
		);
	}

	/**
	 * Views this site may show. The timeline needs targets to be public.
	 *
	 * @return array<string,string>
	 */
	public static function available() {
		$views = self::view_labels();

		if ( ! niroroadmap_get_setting( 'show_target' ) ) {
			unset( $views['timeline'] );
		}

		return $views;
	}

	/**
	 * Work out what a board gets.
	 *
	 * @param array $args `view` (`board` / `list` / `timeline`), `switcher` (`yes` / `no`), `group`
	 *                    (`quarter` / `month` / `nownext`). Empty or missing values follow
	 *                    Settings -> Views.
	 * @return array {
	 *     @type string $default  The view shown first.
	 *     @type array  $views    Views the board can offer, slug => label.
	 *     @type bool   $switcher Whether visitors get the switcher.
	 *     @type array  $render   Views put in the page. Without a switcher that is the default view,
	 *                            plus the list when it is the default on narrow screens.
	 *     @type string $mobile   View shown first on narrow screens, or '' for the default.
	 *     @type string $group    How the timeline groups its items.
	 * }
	 */
	public static function config( $args = array() ) {
		$views = self::available();

		// A view the board can't offer falls back, rather than showing a view with no way to reach it.
		$default = strtolower( (string) ( $args['view'] ?? '' ) );
		if ( ! isset( $views[ $default ] ) ) {
			$default = (string) niroroadmap_get_setting( 'view_default' );
		}
		if ( ! isset( $views[ $default ] ) ) {
			$default = 'board';
		}

		$switcher = strtolower( (string) ( $args['switcher'] ?? '' ) );
		$switcher = in_array( $switcher, array( 'yes', 'no' ), true ) ? 'yes' === $switcher : (bool) niroroadmap_get_setting( 'view_switcher' );
		$switcher = $switcher && count( $views ) > 1;

		$mobile = '';
		if ( niroroadmap_get_setting( 'view_mobile_list' ) && isset( $views['list'] ) && 'list' !== $default ) {
			$mobile = 'list';
		}

		$render = $switcher ? array_keys( $views ) : array_values( array_unique( array_filter( array( $default, $mobile ) ) ) );
		$render = array_values( array_intersect( self::VIEWS, $render ) );

		$group = strtolower( (string) ( $args['group'] ?? '' ) );
		if ( ! in_array( $group, self::GROUPS, true ) ) {
			$group = (string) niroroadmap_get_setting( 'view_timeline_group' );
		}
		if ( ! in_array( $group, self::GROUPS, true ) ) {
			$group = 'quarter';
		}

		return array(
			'default'  => $default,
			'views'    => $views,
			'switcher' => $switcher,
			'render'   => $render,
			'mobile'   => $mobile,
			'group'    => $group,
		);
	}

	/**
	 * Group items by when they are planned.
	 *
	 * Groups come in time order, and only groups with items appear. Items without a usable target
	 * go last, under "Later / Unscheduled" ("Later" in the Now / Next / Later preset). A quarter
	 * typed as a target ("Q4 2026") stays a quarter even when grouping by month, since it doesn't
	 * name a month.
	 *
	 * @param array  $items `target` (as typed), `target_sort` (YYYY-MM-DD or ''), `index` (the
	 *                      board's order, used to break ties).
	 * @param string $mode  One of GROUPS.
	 * @param string $today Today's date (YYYY-MM-DD) in the site's timezone. Decides "Now".
	 * @return array[] Each `key`, `label`, `items`.
	 */
	public static function timeline_groups( $items, $mode, $today ) {
		$buckets = array();
		$current = self::quarter_number( $today );

		foreach ( $items as $item ) {
			$sort = (string) ( $item['target_sort'] ?? '' );
			$date = Fields::is_date( $sort ) ? $sort : '';
			$year = $date ? (int) substr( $date, 0, 4 ) : 0;
			$mon  = $date ? (int) substr( $date, 5, 2 ) : 0;
			$qtr  = $date ? intdiv( $mon - 1, 3 ) + 1 : 0;
			$is_q = $date && (bool) preg_match( '/^Q[1-4] \d{4}$/', (string) ( $item['target'] ?? '' ) );

			if ( ! $date ) {
				$key   = '9';
				$label = 'nownext' === $mode ? __( 'Later', 'niroroadmap' ) : __( 'Later / Unscheduled', 'niroroadmap' );

				if ( 'nownext' === $mode ) {
					$key = '3';
				}
			} elseif ( 'nownext' === $mode ) {
				$ahead = self::quarter_number( $date ) - $current;

				if ( $ahead < 0 ) {
					$key   = '0';
					$label = __( 'Earlier', 'niroroadmap' );
				} elseif ( 0 === $ahead ) {
					$key   = '1';
					$label = __( 'Now', 'niroroadmap' );
				} elseif ( 1 === $ahead ) {
					$key   = '2';
					$label = __( 'Next', 'niroroadmap' );
				} else {
					$key   = '3';
					$label = __( 'Later', 'niroroadmap' );
				}
			} elseif ( 'month' === $mode && ! $is_q ) {
				$key   = sprintf( '%04d-%02d-1', $year, $mon );
				$label = self::format_month( $year, $mon );
			} elseif ( 'month' === $mode ) {
				// Sorts just before the quarter's first month.
				$key   = sprintf( '%04d-%02d-0', $year, $mon );
				/* translators: 1: quarter number (1-4), 2: year. */
				$label = sprintf( __( 'Q%1$d %2$d', 'niroroadmap' ), $qtr, $year );
			} else {
				$key = sprintf( '%04d-%d', $year, $qtr );
				/* translators: 1: quarter number (1-4), 2: year. */
				$label = sprintf( __( 'Q%1$d %2$d', 'niroroadmap' ), $qtr, $year );
			}

			if ( ! isset( $buckets[ $key ] ) ) {
				$buckets[ $key ] = array(
					'key'   => $key,
					'label' => $label,
					'items' => array(),
				);
			}

			$item['target_sort']       = $date;
			$buckets[ $key ]['items'][] = $item;
		}

		ksort( $buckets, SORT_STRING );

		foreach ( $buckets as &$bucket ) {
			usort(
				$bucket['items'],
				static function ( $a, $b ) {
					// Dated items first, then in date order; ties keep the board's order.
					return array( '' === $a['target_sort'], $a['target_sort'], $a['index'] ?? 0 ) <=> array( '' === $b['target_sort'], $b['target_sort'], $b['index'] ?? 0 );
				}
			);
		}
		unset( $bucket );

		return array_values( $buckets );
	}

	/**
	 * The data attributes the popup, the toolbar and the list's sorting read from an item in the
	 * list or the timeline. Already escaped.
	 *
	 * @param array $item        One of Roadmap's flat items.
	 * @param array $context     `toolbar` (bool), `show_tags` (bool), `show_target` (bool).
	 * @return string Attributes, with a leading space.
	 */
	public static function item_attributes( $item, $context ) {
		$color = sanitize_hex_color( (string) $item['stage_color'] ) ?: '#94a3b8';
		$attrs = array(
			'data-task'        => $item['id'],
			'data-tags'        => wp_json_encode( ! empty( $context['show_tags'] ) ? $item['tags'] : array() ),
			'data-stage-id'    => $item['stage_id'],
			'data-stage-name'  => $item['stage_name'],
			'data-stage-color' => $color,
		);

		if ( ! empty( $context['show_target'] ) ) {
			$attrs['data-target-sort'] = $item['target_sort'];
		}

		if ( ! empty( $context['toolbar'] ) ) {
			$attrs['data-date']        = $item['date'];
			$attrs['data-product-ids'] = implode( ' ', array_keys( $item['products'] ) );

			if ( ! empty( $context['show_tags'] ) ) {
				$attrs['data-tag-slugs'] = implode( ' ', $item['tag_slugs'] );
			}
		}

		$html = '';
		foreach ( $attrs as $name => $value ) {
			$html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}

		return $html;
	}

	/**
	 * Count quarters from year 0, so two dates can be compared by quarter.
	 *
	 * @param string $date YYYY-MM-DD.
	 */
	private static function quarter_number( $date ) {
		return (int) substr( $date, 0, 4 ) * 4 + intdiv( (int) substr( $date, 5, 2 ) - 1, 3 );
	}

	/**
	 * "October 2026", in the site's language.
	 */
	private static function format_month( $year, $month ) {
		return wp_date( 'F Y', strtotime( sprintf( '%04d-%02d-15 12:00:00 UTC', $year, $month ) ), new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * Load the script and stylesheet of the list and timeline views and the switcher. Called while a
	 * board renders, so a board that only shows the Board loads neither.
	 */
	public static function enqueue() {
		if ( ! wp_script_is( 'niroroadmap-views', 'registered' ) ) {
			wp_register_style( 'niroroadmap-views', NIROROADMAP_ASSETS_URL . 'public/css/views.css', array( 'niroroadmap-public' ), NIROROADMAP_VERSION );
			wp_register_script( 'niroroadmap-views', NIROROADMAP_ASSETS_URL . 'public/js/views.js', array( 'jquery', 'niroroadmap-public' ), NIROROADMAP_VERSION, true );
		}

		wp_enqueue_style( 'niroroadmap-views' );
		wp_enqueue_script( 'niroroadmap-views' );
	}
}
