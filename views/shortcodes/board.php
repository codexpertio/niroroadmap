<?php
/**
 * The Board view: one column per status.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$tasks            = $args['tasks'] ?? array();
$views            = $args['views'];
$show_stage_links = $args['show_stage_links'] ?? false;
$show_vote_counts = $args['show_vote_counts'] ?? true;
$show_tags        = $args['show_tags'] ?? true;
$comments_enabled = $args['comments_enabled'] ?? false;
$toolbar          = $args['toolbar'] ?? array();
$toolbar_on       = ! empty( $toolbar['enabled'] );

?>
<div class="nr-kanban-columns" data-nr-view="board"<?php echo 'board' === $views['default'] ? '' : ' hidden'; ?>>
	<?php foreach ( $tasks as $slug => $column ) : ?>
		<section class="nr-kanban-column" id="nr-stage-<?php echo esc_attr( $column['id'] ); ?>" data-stage="<?php echo esc_attr( $column['id'] ); ?>" style="--nr-stage-color: <?php echo esc_attr( sanitize_hex_color( $column['color'] ) ?: '#94a3b8' ); ?>;">

			<header class="nr-kanban-column-header">
				<span class="nr-stage-dot" aria-hidden="true"></span>
				<h3 class="nr-stage-name">
					<?php if ( $show_stage_links ) : ?>
						<a href="<?php echo esc_url( get_term_link( (int) $column['id'], 'niroroadmap_status' ) ); ?>"><?php echo esc_html( $column['name'] ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $column['name'] ); ?>
					<?php endif; ?>
				</h3>
				<span class="nr-stage-count"><?php echo esc_html( count( $column['tasks'] ) ); ?></span>
			</header>

			<div class="nr-kanban-list" data-empty="<?php esc_attr_e( 'Nothing here yet', 'niroroadmap' ); ?>"<?php echo $toolbar_on ? ' data-nomatch="' . esc_attr__( 'No matching items', 'niroroadmap' ) . '"' : ''; ?>>
				<?php foreach ( $column['tasks'] as $task_id => $task ) : ?>
					<article class="nr-kanban-item" id="nr-task-<?php echo esc_attr( $task_id ); ?>" data-task="<?php echo esc_attr( $task_id ); ?>" tabindex="0" role="button" aria-haspopup="dialog" data-tags="<?php echo esc_attr( wp_json_encode( $show_tags ? $task['tags'] : array() ) ); ?>"<?php echo $task['pinned'] ? ' data-pinned="1"' : ''; ?><?php if ( $toolbar_on ) : ?> data-date="<?php echo esc_attr( $task['date'] ); ?>" data-product-ids="<?php echo esc_attr( implode( ' ', array_keys( $task['products'] ) ) ); ?>"<?php if ( $show_tags ) : ?> data-tag-slugs="<?php echo esc_attr( implode( ' ', $task['tag_slugs'] ) ); ?>"<?php endif; ?><?php endif; ?>>
						<h4 class="nr-task-title"><?php echo esc_html( $task['title'] ); ?></h4>
						<?php if ( $task['pinned'] ) : ?>
							<span class="nr-task-pin" title="<?php esc_attr_e( 'Pinned', 'niroroadmap' ); ?>">
								<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M12 2l6 6-2 1-3 3 .5 4-1.5 1.5-3.5-3.5L4 17.5 2.5 16 6 12.5 2.5 9 4 7.5l4 .5 3-3z" fill="currentColor"/></svg>
								<span class="nr-sr-only"><?php esc_html_e( 'Pinned', 'niroroadmap' ); ?></span>
							</span>
						<?php endif; ?>

						<div class="nr-task-meta">
							<?php if ( $show_tags && ! empty( $task['tags'] ) ) : ?>
								<ul class="nr-tags">
									<?php foreach ( $task['tags'] as $tag ) : ?>
										<li class="nr-tag"><?php echo esc_html( $tag ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>

							<?php if ( $comments_enabled ) : ?>
								<span class="nr-task-comments" title="<?php esc_attr_e( 'Comments', 'niroroadmap' ); ?>">
									<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 4h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H9l-4 3v-3H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z" fill="currentColor"/></svg>
									<span class="nr-task-comments-count"><?php echo esc_html( $task['comments'] ); ?></span>
									<span class="screen-reader-text nr-sr-only"><?php esc_html_e( 'comments', 'niroroadmap' ); ?></span>
								</span>
							<?php endif; ?>

							<?php if ( $show_vote_counts && ! $task['hide_votes'] ) : ?>
								<span class="nr-task-votes" title="<?php esc_attr_e( 'Upvotes', 'niroroadmap' ); ?>">
									<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 4l6 8H4z" fill="currentColor"/></svg>
									<span class="nr-task-votes-count"><?php echo esc_html( $task['upvotes'] ); ?></span>
								</span>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>
</div>
