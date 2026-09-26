<?php
/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Model\Roadmap;


//TODO: add product attribute
//echo get_block_wrapper_attributes();
?>

<?php echo Roadmap::get_roadmap(); ?>
