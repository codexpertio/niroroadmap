<?php
namespace NiroRoadmap\Model;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Helper\Utility;

class Roadmap {

    public static function get_roadmap( $product = null ) {
        // The shortcode / block can name a product; otherwise use the one chosen in Settings.
        if ( is_null( $product ) && niroroadmap_get_setting( 'default_product' ) ) {
            $product = niroroadmap_get_setting( 'default_product' );
        }

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
					'tags'    => is_array( $tags ) ? wp_list_pluck( $tags, 'name' ) : array(),
				);
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
		) );
    }
}