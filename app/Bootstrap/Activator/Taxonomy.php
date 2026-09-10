<?php
namespace NiroRoadmap\Bootstrap\Activator;

defined( 'ABSPATH' ) || exit;

class Taxonomy {

	public function register() {

		/**
		 * STATUSES
		 */

		$category_labels = array(
			'name'              => _x( 'Statuses', 'taxonomy general name', 'niroroadmap' ),
			'singular_name'     => _x( 'Status', 'taxonomy singular name', 'niroroadmap' ),
			'search_items'      => __( 'Search Statuses', 'niroroadmap' ),
			'all_items'         => __( 'All Statuses', 'niroroadmap' ),
			'parent_item'       => __( 'Parent Status', 'niroroadmap' ),
			'parent_item_colon' => __( 'Parent Status:', 'niroroadmap' ),
			'edit_item'         => __( 'Edit Status', 'niroroadmap' ),
			'update_item'       => __( 'Update Status', 'niroroadmap' ),
			'add_new_item'      => __( 'Add New Status', 'niroroadmap' ),
			'new_item_name'     => __( 'New Status Name', 'niroroadmap' ),
			'menu_name'         => __( 'Statuses', 'niroroadmap' ),
		);

		$category_args = array(
			'hierarchical'      => true,
			'labels'            => $category_labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'product-cat' ),
			'show_in_rest'      => true,
		);

		register_taxonomy( 'niroroadmap_status', array( 'niroroadmap_item' ), $category_args );

		/**
		 * PRODUCTS
		 */

		$brand_labels = array(
			'name'              => _x( 'Products', 'taxonomy general name', 'niroroadmap' ),
			'singular_name'     => _x( 'Product', 'taxonomy singular name', 'niroroadmap' ),
			'search_items'      => __( 'Search Products', 'niroroadmap' ),
			'all_items'         => __( 'All Products', 'niroroadmap' ),
			'parent_item'       => __( 'Parent Product', 'niroroadmap' ),
			'parent_item_colon' => __( 'Parent Product:', 'niroroadmap' ),
			'edit_item'         => __( 'Edit Product', 'niroroadmap' ),
			'update_item'       => __( 'Update Product', 'niroroadmap' ),
			'add_new_item'      => __( 'Add New Product', 'niroroadmap' ),
			'new_item_name'     => __( 'New Product Name', 'niroroadmap' ),
			'menu_name'         => __( 'Products', 'niroroadmap' ),
		);

		$brand_args = array(
			'hierarchical'      => true,
			'labels'            => $brand_labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'product-brand' ),
			'show_in_rest'      => true,
		);

		register_taxonomy( 'niroroadmap_product', array( 'niroroadmap_item' ), $brand_args );

		/**
		 * TAGS
		 * (No textdomain so the translations of the taxonomy tags is used)
		 */

		$tag_labels = array(
			'name' => _x( 'Tags', 'taxonomy general name' ),
			'singular_name' => _x( 'Tag', 'taxonomy singular name' ),
			'search_items' =>  __( 'Search Tags' ),
			'popular_items' => __( 'Popular Tags' ),
			'all_items' => __( 'All Tags' ),
			'parent_item' => null,
			'parent_item_colon' => null,
			'edit_item' => __( 'Edit Tag' ), 
			'update_item' => __( 'Update Tag' ),
			'add_new_item' => __( 'Add New Tag' ),
			'new_item_name' => __( 'New Tag Name' ),
			'separate_items_with_commas' => __( 'Separate tags with commas' ),
			'add_or_remove_items' => __( 'Add or remove tags' ),
			'choose_from_most_used' => __( 'Choose from the most used tags' ),
			'menu_name' => __( 'Tags' ),
		);
		
		$tag_args = array(
			'hierarchical'      => false,
			'labels'            => $tag_labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'product-tag' ),
			'show_in_rest'      => true,
		);

		register_taxonomy( 'niroroadmap_tag', array( 'niroroadmap_item' ), $tag_args );
	}
}
