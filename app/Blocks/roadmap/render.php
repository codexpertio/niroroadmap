<?php
/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Model\Roadmap;


//TODO: add product attribute
//echo get_block_wrapper_attributes();

// '' follows Settings -> Submissions; 'yes' / 'no' override it for this block.
$niroroadmap_submissions = isset( $attributes['submissions'] ) ? sanitize_key( $attributes['submissions'] ) : '';

// Same idea for the toolbar. `filters` is a comma list, so it keeps its commas.
$niroroadmap_toolbar = array(
	'toolbar' => isset( $attributes['toolbar'] ) ? sanitize_key( $attributes['toolbar'] ) : '',
	'sort'    => isset( $attributes['sort'] ) ? sanitize_key( $attributes['sort'] ) : '',
	'filters' => isset( $attributes['filters'] ) ? preg_replace( '/[^a-z,]/', '', strtolower( $attributes['filters'] ) ) : '',
);
?>

<?php echo Roadmap::get_roadmap( null, array( 'submissions' => $niroroadmap_submissions ) + $niroroadmap_toolbar ); ?>
