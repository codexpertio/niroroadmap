<?php
namespace NiroRoadmap\Controller\Admin;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Model\Fields;
use NiroRoadmap\Model\Status_Log;
use NiroRoadmap\Trait\Hook;

/**
 * The item edit screen and Items list: the fields box, the status history box, and the Target and
 * Votes columns.
 */
class Item_Fields {

	use Hook;

	const NONCE = 'niroroadmap_item_fields';

	/**
	 * Constructor to add all hooks.
	 */
	public function __construct() {
		$this->action( 'add_meta_boxes_niroroadmap_item', array( $this, 'add_boxes' ) );
		$this->action( 'save_post_niroroadmap_item', array( $this, 'save' ), 10, 2 );
		$this->action( 'admin_notices', array( $this, 'show_errors' ) );
		$this->action( 'enqueue_block_editor_assets', array( $this, 'editor_notices' ) );

		$this->filter( 'manage_niroroadmap_item_posts_columns', array( $this, 'add_columns' ), 20 );
		$this->action( 'manage_niroroadmap_item_posts_custom_column', array( $this, 'show_column' ), 10, 2 );
		$this->filter( 'manage_edit-niroroadmap_item_sortable_columns', array( $this, 'sortable_columns' ) );
		$this->action( 'pre_get_posts', array( $this, 'sort_list' ) );
	}

	// --- Boxes -----------------------------------------------------------------------------------

	public function add_boxes() {
		add_meta_box( 'niroroadmap_fields', __( 'Roadmap details', 'niroroadmap' ), array( $this, 'render_fields' ), 'niroroadmap_item', 'normal', 'high' );
		add_meta_box( 'niroroadmap_history', __( 'Status history', 'niroroadmap' ), array( $this, 'render_history' ), 'niroroadmap_item', 'side', 'default' );
	}

	public function render_fields( $post ) {
		$get = static function ( $key ) use ( $post ) {
			return (string) get_post_meta( $post->ID, $key, true );
		};

		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="nr-field-target"><?php esc_html_e( 'Target', 'niroroadmap' ); ?></label></th>
				<td>
					<input type="text" id="nr-field-target" name="niroroadmap_fields[target]" value="<?php echo esc_attr( $get( Fields::TARGET ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Q4 2026 or 2026-12-31', 'niroroadmap' ); ?>" aria-describedby="nr-field-target-desc">
					<p class="description" id="nr-field-target-desc"><?php esc_html_e( 'When you plan to ship it: a date, or a quarter. Shown to visitors only if Settings → General says so.', 'niroroadmap' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="nr-field-release"><?php esc_html_e( 'Release date', 'niroroadmap' ); ?></label></th>
				<td>
					<input type="date" id="nr-field-release" name="niroroadmap_fields[release]" value="<?php echo esc_attr( $get( Fields::RELEASE ) ); ?>">
					<label for="nr-field-version" class="screen-reader-text"><?php esc_html_e( 'Version', 'niroroadmap' ); ?></label>
					<input type="text" id="nr-field-version" name="niroroadmap_fields[version]" value="<?php echo esc_attr( $get( Fields::VERSION ) ); ?>" class="regular-text" style="width:9em" placeholder="<?php esc_attr_e( 'Version', 'niroroadmap' ); ?>" maxlength="<?php echo esc_attr( Fields::MAX_VERSION ); ?>">
					<p class="description"><?php esc_html_e( 'When it shipped, and in which version. Shown to visitors.', 'niroroadmap' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="nr-field-link"><?php esc_html_e( 'Link', 'niroroadmap' ); ?></label></th>
				<td>
					<input type="url" id="nr-field-link" name="niroroadmap_fields[link]" value="<?php echo esc_attr( $get( Fields::LINK ) ); ?>" class="large-text" placeholder="https://" aria-describedby="nr-field-link-desc">
					<p class="description" id="nr-field-link-desc"><?php esc_html_e( 'Docs, a pull request or an issue. Shown to visitors.', 'niroroadmap' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="nr-field-effort"><?php esc_html_e( 'Effort', 'niroroadmap' ); ?></label></th>
				<td>
					<select id="nr-field-effort" name="niroroadmap_fields[effort]" aria-describedby="nr-field-effort-desc">
						<option value=""><?php esc_html_e( '— Not set —', 'niroroadmap' ); ?></option>
						<?php foreach ( Fields::efforts() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $get( Fields::EFFORT ), $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description" id="nr-field-effort-desc"><?php esc_html_e( 'Internal. Never shown to visitors.', 'niroroadmap' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="nr-field-notes"><?php esc_html_e( 'Internal notes', 'niroroadmap' ); ?></label></th>
				<td>
					<textarea id="nr-field-notes" name="niroroadmap_fields[notes]" rows="4" class="large-text" maxlength="<?php echo esc_attr( Fields::MAX_NOTES ); ?>" aria-describedby="nr-field-notes-desc"><?php echo esc_textarea( $get( Fields::NOTES ) ); ?></textarea>
					<p class="description" id="nr-field-notes-desc"><?php esc_html_e( 'For your team. Never shown to visitors.', 'niroroadmap' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Display', 'niroroadmap' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Display options', 'niroroadmap' ); ?></legend>
						<label for="nr-field-pinned">
							<input type="checkbox" id="nr-field-pinned" name="niroroadmap_fields[pinned]" value="1" <?php checked( $get( Fields::PINNED ), '1' ); ?>>
							<?php esc_html_e( 'Pin to the top of its column', 'niroroadmap' ); ?>
						</label><br>
						<label for="nr-field-hide-votes">
							<input type="checkbox" id="nr-field-hide-votes" name="niroroadmap_fields[hide_votes]" value="1" <?php checked( $get( Fields::HIDE_VOTES ), '1' ); ?>>
							<?php esc_html_e( 'Hide this item\'s vote counts from visitors', 'niroroadmap' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>
		<p class="description"><?php esc_html_e( 'The cover image is the item\'s Featured image.', 'niroroadmap' ); ?></p>
		<?php
	}

	public function render_history( $post ) {
		$rows = Status_Log::for_item( $post->ID, 'DESC', 10 );

		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No status changes yet.', 'niroroadmap' ) . '</p>';
			return;
		}

		echo '<ul class="niroroadmap-history" style="margin:0">';

		foreach ( $rows as $row ) {
			$user = $row->user_id ? get_userdata( (int) $row->user_id ) : false;
			$who  = $user ? $user->display_name : __( 'a visitor or the system', 'niroroadmap' );
			$when = human_time_diff( strtotime( $row->created_at . ' UTC' ), time() );
			$from = $row->from_name ? $row->from_name : __( 'no status', 'niroroadmap' );
			$to   = $row->to_name ? $row->to_name : __( 'no status', 'niroroadmap' );

			printf(
				'<li style="margin:0 0 8px"><strong>%1$s</strong> &rarr; <strong>%2$s</strong><br><span class="description">%3$s</span></li>',
				esc_html( $from ),
				esc_html( $to ),
				esc_html(
					sprintf(
						/* translators: 1: time since the change, 2: who made it. */
						__( '%1$s ago by %2$s', 'niroroadmap' ),
						$when,
						$who
					)
				)
			);
		}

		echo '</ul>';
	}

	// --- Saving ----------------------------------------------------------------------------------

	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// The form is built so every checkbox and field is present; a partial post is not ours to act on.
		$input = isset( $_POST['niroroadmap_fields'] ) && is_array( $_POST['niroroadmap_fields'] ) ? wp_unslash( $_POST['niroroadmap_fields'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cleaned by Fields::sanitize().
		foreach ( array( 'hide_votes', 'pinned' ) as $checkbox ) {
			$input[ $checkbox ] = $input[ $checkbox ] ?? '0';
		}

		// Fields::sanitize() unslashes too; hand it the raw slashed input.
		$result = Fields::sanitize( wp_slash( $input ) );

		Fields::save( $post_id, $result['values'] );

		if ( $result['errors'] ) {
			set_transient( 'niroroadmap_field_errors_' . get_current_user_id(), $result['errors'], 5 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * The messages saved for this person by the last save, if this is the request that should show them.
	 *
	 * The block editor saves a classic meta box with background requests (the save itself, then the
	 * redirect after it) whose pages are rendered and thrown away. Handing the messages to one of
	 * those would use them up before the person ever sees them. A real page navigation asks for
	 * HTML; the editor's background requests accept anything. That is checked rather than the
	 * Sec-Fetch headers, which browsers only send on secure (HTTPS) pages. Where the Accept header is
	 * missing, assume a real page.
	 *
	 * @return string[] Messages, which are then forgotten.
	 */
	private function take_errors() {
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';

		if ( isset( $_GET['meta-box-loader'] ) || ( '' !== $accept && false === stripos( $accept, 'text/html' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides whether to keep the messages.
			return array();
		}

		$key    = 'niroroadmap_field_errors_' . get_current_user_id();
		$errors = get_transient( $key );

		if ( ! $errors || ! is_array( $errors ) ) {
			return array();
		}

		delete_transient( $key );

		return array_map( 'strval', $errors );
	}

	/**
	 * Classic editor: a normal admin notice. (The block editor doesn't show these; see editor_notices().)
	 */
	public function show_errors() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		// Only on an item's own edit screen (not on whatever admin page comes next), and only the classic one.
		if ( ! $screen || 'niroroadmap_item' !== $screen->post_type || 'post' !== $screen->base || ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) ) {
			return;
		}

		foreach ( $this->take_errors() as $error ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
		}
	}

	/**
	 * Block editor: hand the messages to the editor's own notices.
	 */
	public function editor_notices() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'niroroadmap_item' !== $screen->post_type ) {
			return;
		}

		$errors = $this->take_errors();

		if ( ! $errors ) {
			return;
		}

		wp_add_inline_script(
			'wp-edit-post',
			sprintf(
				'wp.domReady( function () { %s.forEach( function ( message, i ) { wp.data.dispatch( "core/notices" ).createNotice( "warning", message, { id: "niroroadmap-fields-" + i, isDismissible: true } ); } ); } );',
				wp_json_encode( $errors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT )
			)
		);
	}

	// --- Items list ------------------------------------------------------------------------------

	public function add_columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new['niroroadmap_target'] = __( 'Target', 'niroroadmap' );
				$new['niroroadmap_votes']  = __( 'Votes', 'niroroadmap' );
			}
		}

		return $new;
	}

	public function show_column( $column, $post_id ) {
		if ( 'niroroadmap_target' === $column ) {
			$target = (string) get_post_meta( $post_id, Fields::TARGET, true );

			echo '' === $target ? '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'No target', 'niroroadmap' ) . '</span>' : esc_html( Fields::display_date( $target ) );

			if ( Fields::is_pinned( $post_id ) ) {
				echo ' <span title="' . esc_attr__( 'Pinned', 'niroroadmap' ) . '" class="dashicons dashicons-admin-post" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Pinned', 'niroroadmap' ) . '</span>';
			}
		}

		if ( 'niroroadmap_votes' === $column ) {
			printf(
				'<span title="%3$s">&#9650; %1$d</span> <span title="%4$s">&#9660; %2$d</span>',
				(int) get_post_meta( $post_id, 'upvote', true ),
				(int) get_post_meta( $post_id, 'downvote', true ),
				esc_attr__( 'Upvotes', 'niroroadmap' ),
				esc_attr__( 'Downvotes', 'niroroadmap' )
			);

			if ( Fields::votes_hidden( $post_id ) ) {
				echo ' <em class="description">' . esc_html__( '(hidden from visitors)', 'niroroadmap' ) . '</em>';
			}
		}
	}

	public function sortable_columns( $columns ) {
		$columns['niroroadmap_target'] = 'niroroadmap_target';
		$columns['niroroadmap_votes']  = array( 'niroroadmap_votes', true ); // Most votes first on the first click.

		return $columns;
	}

	/**
	 * Sort the Items list by target or votes. Items without a value stay in the list: sorting by a
	 * meta key alone would silently drop every item that doesn't have it yet.
	 */
	public function sort_list( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'niroroadmap_item' !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		$order   = $query->get( 'order' );

		if ( 'niroroadmap_votes' === $orderby ) {
			$clauses = array(
				'relation'    => 'OR',
				'votes_set'   => array(
					'key'     => 'upvote',
					'type'    => 'NUMERIC',
					'compare' => 'EXISTS',
				),
				'votes_unset' => array(
					'key'     => 'upvote',
					'compare' => 'NOT EXISTS',
				),
			);
			$sort_by = array( 'votes_set' => $order ? $order : 'DESC' );
		} elseif ( 'niroroadmap_target' === $orderby ) {
			$clauses = array(
				'relation'     => 'OR',
				'target_set'   => array(
					'key'     => Fields::TARGET_SORT,
					'compare' => 'EXISTS',
				),
				'target_unset' => array(
					'key'     => Fields::TARGET_SORT,
					'compare' => 'NOT EXISTS',
				),
			);
			$sort_by = array( 'target_set' => $order ? $order : 'ASC' );
		} else {
			return;
		}

		// Keep any meta query another filter already set, rather than replacing it.
		$existing = $query->get( 'meta_query' );
		$query->set( 'meta_query', $existing ? array( 'relation' => 'AND', $existing, $clauses ) : $clauses );
		$query->set( 'orderby', $sort_by );
	}
}
