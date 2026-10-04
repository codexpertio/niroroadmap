<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$tasks = $args['tasks'] ?? array();
$show_stage_links = $args['show_stage_links'] ?? false;
$show_vote_counts = $args['show_vote_counts'] ?? true;
$show_tags        = $args['show_tags'] ?? true;
$show_downvote    = $args['show_downvote'] ?? true;
$comments_enabled = $args['comments_enabled'] ?? false;
$submissions_enabled = $args['submissions_enabled'] ?? false;
$submission_product  = $args['submission_product'] ?? 0;
$toolbar             = $args['toolbar'] ?? array();
$toolbar_on          = ! empty( $toolbar['enabled'] );
$board_id            = $args['board_id'] ?? 'nr-board';

?>
<div class="nr-board<?php echo $toolbar_on ? ' nr-has-toolbar' : ''; ?>"<?php echo $toolbar_on ? ' data-nr-toolbar data-nr-default-sort="' . esc_attr( $toolbar['sort'] ) . '"' : ''; ?>>
<?php if ( $submissions_enabled ) : ?>
	<div class="nr-board-toolbar">
		<button type="button" class="nr-suggest-btn" aria-haspopup="dialog" data-product="<?php echo esc_attr( $submission_product ); ?>">
			<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 3a1 1 0 0 1 1 1v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H4a1 1 0 1 1 0-2h5V4a1 1 0 0 1 1-1z" fill="currentColor"/></svg>
			<?php esc_html_e( 'Suggest an idea', 'niroroadmap' ); ?>
		</button>
	</div>
<?php endif; ?>

<?php if ( $toolbar_on ) : ?>
	<?php echo \NiroRoadmap\Helper\Utility::get_template( 'shortcodes/toolbar.php', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its own output. ?>
<?php endif; ?>

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

			<div class="nr-kanban-list" data-empty="<?php esc_attr_e( 'Nothing here yet', 'niroroadmap' ); ?>"<?php echo $toolbar_on ? ' data-nomatch="' . esc_attr__( 'No matching items', 'niroroadmap' ) . '"' : ''; ?>>
				<?php foreach ( $column['tasks'] as $task_id => $task ) : ?>
					<article class="nr-kanban-item" id="nr-task-<?php echo esc_attr( $task_id ); ?>" tabindex="0" role="button" aria-haspopup="dialog" data-tags="<?php echo esc_attr( wp_json_encode( $show_tags ? $task['tags'] : array() ) ); ?>"<?php if ( $toolbar_on ) : ?> data-date="<?php echo esc_attr( $task['date'] ); ?>" data-product-ids="<?php echo esc_attr( implode( ' ', array_keys( $task['products'] ) ) ); ?>"<?php if ( $show_tags ) : ?> data-tag-slugs="<?php echo esc_attr( implode( ' ', $task['tag_slugs'] ) ); ?>"<?php endif; ?><?php endif; ?>>
						<h4 class="nr-task-title"><?php echo esc_html( $task['title'] ); ?></h4>

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

							<?php if ( $show_vote_counts ) : ?>
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
</div><!-- .nr-board -->

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
				<?php if ( $show_downvote ) : ?>
					<button type="button" id="nr-downvote" data-type="downvote" class="nr-vote-btn">
						<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 16L4 8h12z" fill="currentColor"/></svg>
						<?php esc_html_e( 'Downvote', 'niroroadmap' ); ?>
						<span class="nr-vote-count" id="nr-downvote-count">0</span>
					</button>
				<?php endif; ?>
			</div>
			<p class="nr-vote-notice" id="nr-vote-notice" role="status" aria-live="polite"></p>

			<div id="nr-modal-description" class="nr-modal-description"></div>

			<?php if ( $comments_enabled ) : ?>
				<section class="nr-comments" id="nr-comments" aria-labelledby="nr-comments-title" hidden>
					<h3 class="nr-comments-title" id="nr-comments-title"><?php esc_html_e( 'Comments', 'niroroadmap' ); ?> <span class="nr-comments-count" id="nr-comments-count"></span></h3>

					<p class="nr-sr-only" id="nr-comments-live" role="status" aria-live="polite"></p>
					<p class="nr-comments-note" id="nr-comments-note"></p>

					<ol class="nr-comments-list" id="nr-comments-list" aria-labelledby="nr-comments-title"></ol>
					<button type="button" class="nr-comments-more" id="nr-comments-more" hidden><?php esc_html_e( 'Load more comments', 'niroroadmap' ); ?></button>

					<form class="nr-comment-form" id="nr-comment-form" novalidate hidden>
						<p class="nr-replying" id="nr-replying" hidden>
							<span id="nr-replying-to"></span>
							<button type="button" class="nr-link-btn" id="nr-reply-cancel"><?php esc_html_e( 'Cancel', 'niroroadmap' ); ?></button>
						</p>
						<input type="hidden" name="parent" id="nr-comment-parent" value="0">

						<div class="nr-field nr-guest-field">
							<label for="nr-comment-name"><?php esc_html_e( 'Name', 'niroroadmap' ); ?> <span class="nr-required" id="nr-name-required" aria-hidden="true">*</span></label>
							<input type="text" name="name" id="nr-comment-name" autocomplete="name" maxlength="245">
						</div>
						<div class="nr-field nr-guest-field">
							<label for="nr-comment-email"><?php esc_html_e( 'Email', 'niroroadmap' ); ?> <span class="nr-required" id="nr-email-required" aria-hidden="true">*</span></label>
							<input type="email" name="email" id="nr-comment-email" autocomplete="email" maxlength="100" aria-describedby="nr-email-hint">
							<small id="nr-email-hint"><?php esc_html_e( 'Never shown publicly.', 'niroroadmap' ); ?></small>
						</div>
						<div class="nr-field">
							<label for="nr-comment-text"><?php esc_html_e( 'Your comment', 'niroroadmap' ); ?> <span class="nr-required" aria-hidden="true">*</span></label>
							<textarea name="content" id="nr-comment-text" rows="4" required aria-describedby="nr-comment-error"></textarea>
						</div>

						<?php // Spam trap: hidden from people, tempting to bots. ?>
						<div class="nr-hp" aria-hidden="true">
							<label for="nr-comment-website"><?php esc_html_e( 'Website', 'niroroadmap' ); ?></label>
							<input type="text" name="website" id="nr-comment-website" tabindex="-1" autocomplete="off">
						</div>

						<p class="nr-comment-error" id="nr-comment-error" role="alert"></p>
						<button type="submit" class="nr-comment-submit" id="nr-comment-submit"><?php esc_html_e( 'Post comment', 'niroroadmap' ); ?></button>
					</form>
				</section>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php endif; ?>

<?php if ( $submissions_enabled && ! did_action( 'niroroadmap_suggest_rendered' ) ) : ?>
<?php do_action( 'niroroadmap_suggest_rendered' ); ?>
<?php echo \NiroRoadmap\Helper\Utility::get_template( 'shortcodes/suggest.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes its own output. ?>
<?php endif; ?>
