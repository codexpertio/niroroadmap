<?php
namespace NiroRoadmap\Bootstrap\Activator;

defined( 'ABSPATH' ) || exit;

class Post_Type {

	public function register() {
		$labels = array(
			'name'               => _x( 'Items', 'post type general name', 'niroroadmap' ),
			'singular_name'      => _x( 'Item', 'post type singular name', 'niroroadmap' ),
			'menu_name'          => _x( 'NiroRoadmap', 'admin menu', 'niroroadmap' ),
			'name_admin_bar'     => _x( 'Item', 'add new on admin bar', 'niroroadmap' ),
			'add_new'            => _x( 'Add New', 'item', 'niroroadmap' ),
			'add_new_item'       => __( 'Add New', 'niroroadmap' ),
			'new_item'           => __( 'New Item', 'niroroadmap' ),
			'edit_item'          => __( 'Edit Item', 'niroroadmap' ),
			'view_item'          => __( 'View Item', 'niroroadmap' ),
			'all_items'          => __( 'Items', 'niroroadmap' ),
			'search_items'       => __( 'Search Items', 'niroroadmap' ),
			'parent_item_colon'  => __( 'Parent Items:', 'niroroadmap' ),
			'not_found'          => __( 'No items found.', 'niroroadmap' ),
			'not_found_in_trash' => __( 'No items found in Trash.', 'niroroadmap' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			// 'show_in_menu'       => 'store',
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'tasks' ),
			'capability_type'    => 'page',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 2,
			'menu_icon'          => 'dashicons-calendar-alt',
			'supports'           => array( 'title', 'editor', 'author', 'comments' ),
			'show_in_rest'       => true, // needed for block editor
		);

		register_post_type( 'niroroadmap_item', $args );
	}
}
