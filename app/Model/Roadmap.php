<?php
namespace NiroRoadmap\Model;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Helper\Utility;

class Roadmap {

    /**
     * Only published, non-password-protected roadmap items are public.
     *
     * @param int $id Item ID.
     * @return \WP_Post|null
     */
    public static function get_public_item( $id ) {
        $item = get_post( (int) $id );

        if ( ! $item || 'niroroadmap_item' !== $item->post_type || 'publish' !== $item->post_status || '' !== $item->post_password ) {
            return null;
        }

        return $item;
    }

    /**
     * Render the board.
     *
     * @param int|string|null $product Product term ID to show, or null for the site default.
     * @param array           $args    `submissions`, `toolbar`, `switcher`: `yes` / `no` to override the site setting.
     *                                 `sort`, `filters`: the toolbar's initial sort and which filters it offers.
     *                                 `view`, `group`: the view a board opens in and how its timeline groups items.
     * @return string
     */
    public static function get_roadmap( $product = null, $args = array() ) {
        // The shortcode / block can name a product; otherwise use the one chosen in Settings.
        if ( is_null( $product ) && niroroadmap_get_setting( 'default_product' ) ) {
            $product = niroroadmap_get_setting( 'default_product' );
        }

        // Decided up front: what the toolbar needs is only fetched when there is a toolbar.
        $toolbar = Toolbar::config( $args );
        $views   = Views::config( $args );

        // Targets are only read for the views that show them, and only when the site shows targets.
        $with_target = niroroadmap_get_setting( 'show_target' ) && array_intersect( array( 'list', 'timeline' ), $views['render'] );

        $tasks  = array();
		$stages = get_terms(
			array(
				'taxonomy'   => 'niroroadmap_status',
				'hide_empty' => false,
				'orderby'    => 'id',
				'order'      => 'ASC',
			)
		);

		foreach ( $stages as $stage ) {
			$tasks[ $stage->slug ]['id']    = $stage->term_id;
			$tasks[ $stage->slug ]['name']  = $stage->name;
			$tasks[ $stage->slug ]['color'] = get_term_meta( $stage->term_id, 'color', true );

			$tax_query = array();

			$tax_query[] = array(
				'taxonomy' => 'niroroadmap_status',
				'field'    => 'slug',
				'terms'    => $stage->slug,
			);

			if ( ! is_null( $product ) ) {
				$tax_query[] = array(
					'taxonomy' => 'niroroadmap_product',
					'field'    => 'term_id',
					'terms'    => $product,
				);
			}

			$posts = Utility::get_posts(
				array(
					'post_type'      => 'niroroadmap_item',
					'tax_query'      => $tax_query,
					'posts_per_page' => -1,
					'orderby'        => 'menu_order',
					'order'          => 'ASC',
				)
			);

			$tasks[ $stage->slug ]['tasks'] = array();
			foreach ( $posts as $task_id => $task_title ) {
				$tags = get_the_terms( $task_id, 'niroroadmap_tag' );

				$tasks[ $stage->slug ]['tasks'][ $task_id ] = array(
					'title'   => $task_title,
					'upvotes' => (int) get_post_meta( $task_id, 'upvote', true ),
					'comments' => (int) get_comments_number( $task_id ),
					'tags'    => is_array( $tags ) ? wp_list_pluck( $tags, 'name' ) : array(),
					'tag_slugs' => is_array( $tags ) ? wp_list_pluck( $tags, 'slug' ) : array(),
					'products'  => array(),
					'date'      => 0,
					'target'      => '',
					'target_sort' => '',
					'pinned'     => Fields::is_pinned( $task_id ),
					'hide_votes' => Fields::votes_hidden( $task_id ),
				);

				if ( $with_target ) {
					$tasks[ $stage->slug ]['tasks'][ $task_id ]['target']      = (string) get_post_meta( $task_id, Fields::TARGET, true );
					$tasks[ $stage->slug ]['tasks'][ $task_id ]['target_sort'] = (string) get_post_meta( $task_id, Fields::TARGET_SORT, true );
				}

				if ( $toolbar['enabled'] ) {
					$products = get_the_terms( $task_id, 'niroroadmap_product' );

					$tasks[ $stage->slug ]['tasks'][ $task_id ]['products'] = is_array( $products ) ? wp_list_pluck( $products, 'name', 'term_id' ) : array();
					$tasks[ $stage->slug ]['tasks'][ $task_id ]['date']     = (int) get_post_time( 'U', true, $task_id );
				}
			}

			// Pinned items lead their column. The sort is stable, so each group keeps the team's manual order.
			uasort(
				$tasks[ $stage->slug ]['tasks'],
				static function ( $a, $b ) {
					return (int) $b['pinned'] <=> (int) $a['pinned'];
				}
			);
		}

		// What the toolbar can offer is what's actually on this board.
		$filter_tags     = array();
		$filter_products = array();

		if ( $toolbar['enabled'] ) {
			Toolbar::enqueue();

			foreach ( $tasks as $column ) {
				foreach ( $column['tasks'] as $task ) {
					foreach ( $task['tag_slugs'] as $i => $slug ) {
						$filter_tags[ $slug ] = $task['tags'][ $i ] ?? $slug;
					}

					$filter_products += $task['products'];
				}
			}

			asort( $filter_tags, SORT_NATURAL | SORT_FLAG_CASE );
			asort( $filter_products, SORT_NATURAL | SORT_FLAG_CASE );
		}

		// The list and the timeline take the same items as the board, in one flat list.
		$items    = array();
		$timeline = array();

		if ( array_intersect( array( 'list', 'timeline' ), $views['render'] ) ) {
			Views::enqueue();

			foreach ( $tasks as $column ) {
				foreach ( $column['tasks'] as $task_id => $task ) {
					$items[] = array(
						'id'          => $task_id,
						'index'       => count( $items ),
						'stage_id'    => $column['id'],
						'stage_name'  => $column['name'],
						'stage_color' => $column['color'],
					) + $task;
				}
			}

			if ( in_array( 'timeline', $views['render'], true ) ) {
				$timeline = Views::timeline_groups( $items, $views['group'], wp_date( 'Y-m-d' ) );
			}
		}

		// The filter keeps working and overrides the setting.
		$show_links = apply_filters( 'niroroadmap_show_stage_links', niroroadmap_get_setting( 'stage_links' ) );

		return Utility::get_template( 'shortcodes/roadmap.php', array( 
			'tasks' => $tasks,
			'show_stage_links' => $show_links,
			'show_vote_counts' => niroroadmap_get_setting( 'show_vote_counts' ),
			'show_tags'        => niroroadmap_get_setting( 'show_tags' ),
			'show_downvote'    => niroroadmap_get_setting( 'show_downvote' ),
			'comments_enabled' => Comment::enabled(),
			'submissions_enabled' => Submission::enabled( $args['submissions'] ?? '' ),
			// A board that shows one product files its ideas under that product.
			'submission_product' => $product ? (int) $product : 0,
			'toolbar'         => $toolbar,
			'views'           => $views,
			'items'           => $items,
			'timeline'        => $timeline,
			'show_target'     => (bool) niroroadmap_get_setting( 'show_target' ),
			'filter_tags'     => $filter_tags,
			'filter_products' => $filter_products,
			// A board that shows one product has nothing to filter by product.
			'product_locked'  => (bool) $product,
			'board_id'        => wp_unique_id( 'nr-board-' ),
		) );
    }
}