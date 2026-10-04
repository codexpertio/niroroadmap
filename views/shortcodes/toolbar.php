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

$submissions_enabled = $args['submissions_enabled'] ?? false;
$submission_product  = $args['submission_product'] ?? 0;
$views               = $args['views'];

$show_search  = ! empty( $filters['search'] );
$show_tags    = ! empty( $filters['tag'] ) && $filter_tags;
$show_product = ! empty( $filters['product'] ) && ! $product_locked && count( $filter_products ) > 1;

?>
<div class="nr-toolbar" role="group" aria-label="<?php esc_attr_e( 'Search, sort and filter the roadmap', 'niroroadmap' ); ?>">
	<?php // One row on wide screens: search, sort, filters and, when the board offers it, the Suggest button. ?>
	<div class="nr-toolbar-bar">
		<?php if ( $show_search ) : ?>
			<div class="nr-tool nr-tool-search">
				<label for="<?php echo esc_attr( $board_id ); ?>-q" class="nr-sr-only"><?php esc_html_e( 'Search ideas', 'niroroadmap' ); ?></label>
				<input type="search" id="<?php echo esc_attr( $board_id ); ?>-q" data-nr-search placeholder="<?php esc_attr_e( 'Search ideas…', 'niroroadmap' ); ?>" autocomplete="off" aria-describedby="<?php echo esc_attr( $board_id ); ?>-status">
			</div>
		<?php endif; ?>

		<button type="button" class="nr-toolbar-toggle" data-nr-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $board_id ); ?>-controls">
			<?php esc_html_e( 'Sort & filter', 'niroroadmap' ); ?>
		</button>

		<div class="nr-toolbar-controls" id="<?php echo esc_attr( $board_id ); ?>-controls">
			<div class="nr-tool nr-tool-inline">
				<label for="<?php echo esc_attr( $board_id ); ?>-sort"><?php esc_html_e( 'Sort', 'niroroadmap' ); ?></label>
				<select id="<?php echo esc_attr( $board_id ); ?>-sort" data-nr-sort>
					<?php foreach ( $toolbar['sorts'] as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $toolbar['sort'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<?php if ( $show_product ) : ?>
				<div class="nr-tool nr-tool-inline">
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
				<?php // A dropdown on wide screens, so the toolbar stays on one row however many tags there are. Phones show the chips inline. ?>
				<div class="nr-tool nr-tool-tags" data-nr-tags>
					<button type="button" class="nr-tags-toggle" data-nr-tags-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $board_id ); ?>-tags">
						<?php esc_html_e( 'Tags', 'niroroadmap' ); ?> <span class="nr-tags-count" data-nr-tags-count hidden></span>
					</button>
					<fieldset class="nr-tags-panel" id="<?php echo esc_attr( $board_id ); ?>-tags" hidden>
						<legend class="nr-sr-only"><?php esc_html_e( 'Filter by tag', 'niroroadmap' ); ?></legend>
						<?php foreach ( $filter_tags as $tag_slug => $tag_name ) : ?>
							<button type="button" class="nr-chip" data-nr-tag="<?php echo esc_attr( $tag_slug ); ?>" aria-pressed="false"><?php echo esc_html( $tag_name ); ?></button>
						<?php endforeach; ?>
					</fieldset>
				</div>
			<?php endif; ?>

			<button type="button" class="nr-toolbar-reset" data-nr-reset hidden><?php esc_html_e( 'Reset', 'niroroadmap' ); ?></button>
		</div>

		<?php if ( $submissions_enabled || $views['switcher'] ) : ?>
			<?php // The view switcher sits just left of the Suggest button, both closing the row on the right. ?>
			<div class="nr-toolbar-end">
				<?php if ( $views['switcher'] ) : ?>
					<?php echo \NiroRoadmap\Helper\Utility::get_template( 'shortcodes/switcher.php', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its own output. ?>
				<?php endif; ?>
				<?php if ( $submissions_enabled ) : ?>
					<button type="button" class="nr-suggest-btn" aria-haspopup="dialog" data-product="<?php echo esc_attr( $submission_product ); ?>">
						<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 3a1 1 0 0 1 1 1v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H4a1 1 0 1 1 0-2h5V4a1 1 0 0 1 1-1z" fill="currentColor"/></svg>
						<?php esc_html_e( 'Suggest an idea', 'niroroadmap' ); ?>
					</button>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

<?php // Not shown, but read out to screen-reader users as results change. The script makes it a live region once it has written the first count, so the page load itself isn't announced. ?>
	<p class="nr-toolbar-status nr-sr-only" id="<?php echo esc_attr( $board_id ); ?>-status" data-nr-status></p>
</div>
