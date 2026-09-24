<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$tasks = $args['tasks'] ?? array();
$show_stage_links = $args['show_stage_links'] ?? false;

?>
<div class="nr-kanban-columns">
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

			<div class="nr-kanban-list" data-empty="<?php esc_attr_e( 'Nothing here yet', 'niroroadmap' ); ?>">
				<?php foreach ( $column['tasks'] as $task_id => $task ) : ?>
					<article class="nr-kanban-item" id="nr-task-<?php echo esc_attr( $task_id ); ?>" tabindex="0" role="button" aria-haspopup="dialog" data-tags="<?php echo esc_attr( wp_json_encode( $task['tags'] ) ); ?>">
						<h4 class="nr-task-title"><?php echo esc_html( $task['title'] ); ?></h4>

						<div class="nr-task-meta">
							<?php if ( ! empty( $task['tags'] ) ) : ?>
								<ul class="nr-tags">
									<?php foreach ( $task['tags'] as $tag ) : ?>
										<li class="nr-tag"><?php echo esc_html( $tag ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>

							<span class="nr-task-votes" title="<?php esc_attr_e( 'Upvotes', 'niroroadmap' ); ?>">
								<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 4l6 8H4z" fill="currentColor"/></svg>
								<span class="nr-task-votes-count"><?php echo esc_html( $task['upvotes'] ); ?></span>
							</span>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>
</div>

<?php if ( ! did_action( 'niroroadmap_modal_rendered' ) ) : ?>
<?php do_action( 'niroroadmap_modal_rendered' ); ?>
<div class="nr-modal-overlay" id="nr-modal-overlay" style="display: none;">
	<div class="nr-modal" id="nr-modal" role="dialog" aria-modal="true" aria-labelledby="nr-modal-title">
		<button type="button" class="nr-close-modal" id="nr-close-modal" aria-label="<?php esc_attr_e( 'Close', 'niroroadmap' ); ?>">&times;</button>

		<div id="nr-modal-content">
			<input type="hidden" id="nr-modal-id">

			<div class="nr-modal-labels">
				<div class="nr-modal-stage" id="nr-modal-stage"><span class="nr-stage-dot" aria-hidden="true"></span><span id="nr-modal-stage-name"></span></div>
				<ul class="nr-tags" id="nr-modal-tags"></ul>
			</div>
			<h2 id="nr-modal-title"></h2>

			<div class="nr-vote-buttons">
				<button type="button" id="nr-upvote" data-type="upvote" class="nr-vote-btn">
					<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 4l6 8H4z" fill="currentColor"/></svg>
					<?php esc_html_e( 'Upvote', 'niroroadmap' ); ?>
					<span class="nr-vote-count" id="nr-upvote-count">0</span>
				</button>
				<button type="button" id="nr-downvote" data-type="downvote" class="nr-vote-btn">
					<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 16L4 8h12z" fill="currentColor"/></svg>
					<?php esc_html_e( 'Downvote', 'niroroadmap' ); ?>
					<span class="nr-vote-count" id="nr-downvote-count">0</span>
				</button>
			</div>

			<div id="nr-modal-description" class="nr-modal-description"></div>
		</div>
	</div>
</div>
<?php endif; ?>
