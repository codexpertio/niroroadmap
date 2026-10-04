<?php
/**
 * The search / sort / filter toolbar of one board.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$toolbar         = $args['toolbar'] ?? array();
$filter_tags     = $args['filter_tags'] ?? array();
$filter_products = $args['filter_products'] ?? array();
$product_locked  = $args['product_locked'] ?? false;
$board_id        = $args['board_id'] ?? 'nr-board';
$filters         = $toolbar['filters'] ?? array();

$show_search  = ! empty( $filters['search'] );
$show_tags    = ! empty( $filters['tag'] ) && $filter_tags;
$show_product = ! empty( $filters['product'] ) && ! $product_locked && count( $filter_products ) > 1;

?>
<div class="nr-toolbar" role="group" aria-label="<?php esc_attr_e( 'Search, sort and filter the roadmap', 'niroroadmap' ); ?>">
	<div class="nr-toolbar-row">
		<?php if ( $show_search ) : ?>
			<div class="nr-tool nr-tool-search">
				<label for="<?php echo esc_attr( $board_id ); ?>-q" class="nr-sr-only"><?php esc_html_e( 'Search ideas', 'niroroadmap' ); ?></label>
				<input type="search" id="<?php echo esc_attr( $board_id ); ?>-q" data-nr-search placeholder="<?php esc_attr_e( 'Search ideas…', 'niroroadmap' ); ?>" autocomplete="off" aria-describedby="<?php echo esc_attr( $board_id ); ?>-status">
			</div>
		<?php endif; ?>

		<button type="button" class="nr-toolbar-toggle" data-nr-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $board_id ); ?>-controls">
			<?php esc_html_e( 'Sort & filter', 'niroroadmap' ); ?>
		</button>
	</div>

	<div class="nr-toolbar-controls" id="<?php echo esc_attr( $board_id ); ?>-controls">
		<div class="nr-tool">
			<label for="<?php echo esc_attr( $board_id ); ?>-sort"><?php esc_html_e( 'Sort', 'niroroadmap' ); ?></label>
			<select id="<?php echo esc_attr( $board_id ); ?>-sort" data-nr-sort>
				<?php foreach ( $toolbar['sorts'] as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $toolbar['sort'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<?php if ( $show_product ) : ?>
			<div class="nr-tool">
				<label for="<?php echo esc_attr( $board_id ); ?>-product"><?php esc_html_e( 'Product', 'niroroadmap' ); ?></label>
				<select id="<?php echo esc_attr( $board_id ); ?>-product" data-nr-product>
					<option value=""><?php esc_html_e( 'All products', 'niroroadmap' ); ?></option>
					<?php foreach ( $filter_products as $product_id => $product_name ) : ?>
						<option value="<?php echo esc_attr( $product_id ); ?>"><?php echo esc_html( $product_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>

		<?php if ( $show_tags ) : ?>
			<fieldset class="nr-tool nr-tool-tags">
				<legend><?php esc_html_e( 'Tags', 'niroroadmap' ); ?></legend>
				<?php foreach ( $filter_tags as $tag_slug => $tag_name ) : ?>
					<button type="button" class="nr-chip" data-nr-tag="<?php echo esc_attr( $tag_slug ); ?>" aria-pressed="false"><?php echo esc_html( $tag_name ); ?></button>
				<?php endforeach; ?>
			</fieldset>
		<?php endif; ?>

		<button type="button" class="nr-toolbar-reset" data-nr-reset hidden><?php esc_html_e( 'Reset', 'niroroadmap' ); ?></button>
	</div>

	<?php // The script makes this a live region once it has written the first count, so the page load itself isn't announced. ?>
	<p class="nr-toolbar-status" id="<?php echo esc_attr( $board_id ); ?>-status" data-nr-status></p>
	<p class="nr-toolbar-note" data-nr-note hidden><?php esc_html_e( 'Reordering is off while a sort or filter is active. Choose "Manual order" and clear the filters to drag cards again.', 'niroroadmap' ); ?></p>
</div>
