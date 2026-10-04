<?php
/**
 * The Timeline view: items grouped by when they are planned.
 */

use NiroRoadmap\Model\Fields;
use NiroRoadmap\Model\Views;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$groups           = $args['timeline'] ?? array();
$views            = $args['views'];
$show_vote_counts = $args['show_vote_counts'] ?? true;
$show_tags        = $args['show_tags'] ?? true;
$comments_enabled = $args['comments_enabled'] ?? false;
$toolbar          = $args['toolbar'] ?? array();
$toolbar_on       = ! empty( $toolbar['enabled'] );
$board_id         = $args['board_id'] ?? 'nr-board';

// The timeline is only built when targets are public.
$context = array(
	'toolbar'     => $toolbar_on,
	'show_tags'   => $show_tags,
	'show_target' => true,
);

?>
<div class="nr-timeline" data-nr-view="timeline"<?php echo 'timeline' === $views['default'] ? '' : ' hidden'; ?>>
<?php if ( ! $groups ) : ?>
	<p class="nr-view-empty"><?php esc_html_e( 'Nothing here yet', 'niroroadmap' ); ?></p>
<?php else : ?>
	<?php foreach ( $groups as $i => $group ) : ?>
		<section class="nr-timeline-group" data-nr-group aria-labelledby="<?php echo esc_attr( $board_id ); ?>-tl-<?php echo esc_attr( $i ); ?>">
			<header class="nr-timeline-heading">
				<h3 id="<?php echo esc_attr( $board_id ); ?>-tl-<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $group['label'] ); ?></h3>
				<span class="nr-group-count"><?php echo esc_html( count( $group['items'] ) ); ?></span>
			</header>

			<ul class="nr-timeline-items">
				<?php foreach ( $group['items'] as $item ) : ?>
					<li class="nr-view-item nr-timeline-item"<?php echo Views::item_attributes( $item, $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the helper. ?>>
						<button type="button" class="nr-task-title nr-open-btn" aria-haspopup="dialog"><?php echo esc_html( $item['title'] ); ?></button>

						<div class="nr-timeline-meta">
							<span class="nr-view-stage" style="--nr-stage-color: <?php echo esc_attr( sanitize_hex_color( (string) $item['stage_color'] ) ?: '#94a3b8' ); ?>;">
								<span class="nr-stage-dot" aria-hidden="true"></span>
								<?php echo esc_html( $item['stage_name'] ); ?>
							</span>

							<?php if ( '' !== $item['target'] ) : ?>
								<span class="nr-timeline-target"><?php echo esc_html( Fields::display_date( $item['target'] ) ); ?></span>
							<?php endif; ?>
						</div>

						<div class="nr-task-meta">
							<?php if ( $show_tags && ! empty( $item['tags'] ) ) : ?>
								<ul class="nr-tags">
									<?php foreach ( $item['tags'] as $tag ) : ?>
										<li class="nr-tag"><?php echo esc_html( $tag ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>

							<?php if ( $comments_enabled ) : ?>
								<span class="nr-task-comments" title="<?php esc_attr_e( 'Comments', 'niroroadmap' ); ?>">
									<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 4h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H9l-4 3v-3H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z" fill="currentColor"/></svg>
									<span class="nr-task-comments-count"><?php echo esc_html( $item['comments'] ); ?></span>
									<span class="nr-sr-only"><?php esc_html_e( 'comments', 'niroroadmap' ); ?></span>
								</span>
							<?php endif; ?>

							<?php if ( $show_vote_counts && ! $item['hide_votes'] ) : ?>
								<span class="nr-task-votes" title="<?php esc_attr_e( 'Upvotes', 'niroroadmap' ); ?>">
									<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 4l6 8H4z" fill="currentColor"/></svg>
									<span class="nr-task-votes-count"><?php echo esc_html( $item['upvotes'] ); ?></span>
								</span>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endforeach; ?>
	<?php if ( $toolbar_on ) : ?>
		<p class="nr-view-empty" data-nr-nomatch hidden><?php esc_html_e( 'No matching items', 'niroroadmap' ); ?></p>
	<?php endif; ?>
<?php endif; ?>
</div>
