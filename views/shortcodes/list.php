<?php
/**
 * The List view: a compact table, one row per item.
 */

use NiroRoadmap\Model\Fields;
use NiroRoadmap\Model\Views;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$items            = $args['items'] ?? array();
$views            = $args['views'];
$show_vote_counts = $args['show_vote_counts'] ?? true;
$show_tags        = $args['show_tags'] ?? true;
$show_target      = $args['show_target'] ?? false;
$comments_enabled = $args['comments_enabled'] ?? false;
$toolbar          = $args['toolbar'] ?? array();
$toolbar_on       = ! empty( $toolbar['enabled'] );

$context = array(
	'toolbar'     => $toolbar_on,
	'show_tags'   => $show_tags,
	'show_target' => $show_target,
);

/**
 * A column header; the sortable ones hold a button.
 */
$column = static function ( $class, $label, $sort_by = '' ) {
	?>
	<th scope="col" class="<?php echo esc_attr( $class ); ?>"<?php echo $sort_by ? ' aria-sort="none"' : ''; ?>>
		<?php if ( $sort_by ) : ?>
			<button type="button" class="nr-sort-btn" data-nr-sort-by="<?php echo esc_attr( $sort_by ); ?>"><?php echo esc_html( $label ); ?></button>
		<?php else : ?>
			<?php echo esc_html( $label ); ?>
		<?php endif; ?>
	</th>
	<?php
};

?>
<div class="nr-list-view" data-nr-view="list"<?php echo 'list' === $views['default'] ? '' : ' hidden'; ?>>
<?php if ( ! $items ) : ?>
	<p class="nr-view-empty"><?php esc_html_e( 'Nothing here yet', 'niroroadmap' ); ?></p>
<?php else : ?>
	<div class="nr-list-scroll">
		<table class="nr-list-table">
			<caption class="nr-sr-only"><?php esc_html_e( 'Roadmap items', 'niroroadmap' ); ?></caption>
			<thead>
				<tr>
					<?php
					$column( 'nr-col-title', __( 'Idea', 'niroroadmap' ), 'title' );
					$column( 'nr-col-status', __( 'Status', 'niroroadmap' ), 'status' );
					if ( $show_tags ) {
						$column( 'nr-col-tags', __( 'Tags', 'niroroadmap' ) );
					}
					if ( $comments_enabled ) {
						$column( 'nr-col-comments', __( 'Comments', 'niroroadmap' ), 'comments' );
					}
					if ( $show_vote_counts ) {
						$column( 'nr-col-votes', __( 'Votes', 'niroroadmap' ), 'votes' );
					}
					if ( $show_target ) {
						$column( 'nr-col-target', __( 'Target', 'niroroadmap' ), 'target' );
					}
					?>
				</tr>
			</thead>
			<tbody class="nr-list-body">
				<?php foreach ( $items as $item ) : ?>
					<tr class="nr-view-item"<?php echo Views::item_attributes( $item, $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the helper. ?>>
						<th scope="row" class="nr-col-title">
							<button type="button" class="nr-task-title nr-open-btn" aria-haspopup="dialog"><?php echo esc_html( $item['title'] ); ?></button>
						</th>
						<td class="nr-col-status">
							<span class="nr-view-stage" style="--nr-stage-color: <?php echo esc_attr( sanitize_hex_color( (string) $item['stage_color'] ) ?: '#94a3b8' ); ?>;">
								<span class="nr-stage-dot" aria-hidden="true"></span>
								<?php echo esc_html( $item['stage_name'] ); ?>
							</span>
						</td>
						<?php if ( $show_tags ) : ?>
							<td class="nr-col-tags">
								<?php if ( ! empty( $item['tags'] ) ) : ?>
									<ul class="nr-tags">
										<?php foreach ( $item['tags'] as $tag ) : ?>
											<li class="nr-tag"><?php echo esc_html( $tag ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</td>
						<?php endif; ?>
						<?php if ( $comments_enabled ) : ?>
							<td class="nr-col-comments">
								<span class="nr-task-comments" title="<?php esc_attr_e( 'Comments', 'niroroadmap' ); ?>">
									<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 4h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H9l-4 3v-3H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z" fill="currentColor"/></svg>
									<span class="nr-task-comments-count"><?php echo esc_html( $item['comments'] ); ?></span>
									<span class="nr-sr-only"><?php esc_html_e( 'comments', 'niroroadmap' ); ?></span>
								</span>
							</td>
						<?php endif; ?>
						<?php if ( $show_vote_counts ) : ?>
							<td class="nr-col-votes">
								<?php if ( ! $item['hide_votes'] ) : ?>
									<span class="nr-task-votes" title="<?php esc_attr_e( 'Upvotes', 'niroroadmap' ); ?>">
										<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 4l6 8H4z" fill="currentColor"/></svg>
										<span class="nr-task-votes-count"><?php echo esc_html( $item['upvotes'] ); ?></span>
									</span>
								<?php endif; ?>
							</td>
						<?php endif; ?>
						<?php if ( $show_target ) : ?>
							<td class="nr-col-target"><?php echo esc_html( Fields::display_date( $item['target'] ) ); ?></td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php if ( $toolbar_on ) : ?>
		<p class="nr-view-empty" data-nr-nomatch hidden><?php esc_html_e( 'No matching items', 'niroroadmap' ); ?></p>
	<?php endif; ?>
<?php endif; ?>
</div>
