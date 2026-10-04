<?php
namespace NiroRoadmap\Model;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Helper\Voter;

/**
 * Ideas suggested by visitors. They are created as `pending` roadmap items, so nothing shows on
 * the public board (or in the public API) until someone approves them.
 */
class Submission {

	const META_FLAG  = '_niroroadmap_submitted';
	const META_NAME  = '_niroroadmap_submitter_name';
	const META_EMAIL = '_niroroadmap_submitter_email';

	/**
	 * Whether a board offers the "Suggest an idea" button.
	 *
	 * @param string $override `yes` or `no` from a shortcode / block, anything else for the site default.
	 */
	public static function enabled( $override = '' ) {
		$override = strtolower( (string) $override );

		if ( in_array( $override, array( 'yes', 'no' ), true ) ) {
			return 'yes' === $override;
		}

		return (bool) niroroadmap_get_setting( 'submissions_enabled' );
	}

	/**
	 * Whether the submission API answers.
	 *
	 * It does whenever the plugin is active: the site-wide setting only decides whether boards
	 * show the button by default, and a board can turn it on with `submissions="yes"`. Whatever
	 * comes in is only ever stored as pending. A site that wants the endpoint closed altogether
	 * can return false from this filter.
	 */
	public static function api_available() {
		return (bool) apply_filters( 'niroroadmap_submissions_api_available', true );
	}

	public static function max_title() {
		return max( 10, (int) apply_filters( 'niroroadmap_submission_max_title', 200 ) );
	}

	public static function max_description() {
		return max( 10, (int) apply_filters( 'niroroadmap_submission_max_description', 2000 ) );
	}

	/**
	 * Shortest time, in milliseconds, between opening the form and sending it.
	 */
	public static function min_time() {
		return max( 0, (int) apply_filters( 'niroroadmap_submission_min_time', 2000 ) );
	}

	/**
	 * Products a visitor can file an idea under.
	 *
	 * @return array<int,string> Term ID => name.
	 */
	public static function products() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'niroroadmap_product',
				'hide_empty' => false,
			)
		);

		return is_array( $terms ) ? wp_list_pluck( $terms, 'name', 'term_id' ) : array();
	}

	/**
	 * Validate and store a visitor's idea.
	 *
	 * @param array $input `title`, `description`, `product`, `name`, `email`.
	 * @return array|\WP_Error `id` of the new pending item.
	 */
	public static function create( $input ) {
		$logged_in = is_user_logged_in();
		$identity  = niroroadmap_get_setting( 'submissions_identity' );

		// Refuse absurdly large input before doing any work on it. Legitimate text is only a little
		// longer than its limit once tags and extra whitespace are counted, so 8x is generous.
		if ( strlen( (string) ( $input['title'] ?? '' ) ) > self::max_title() * 8 ) {
			return new \WP_Error( 'submission_title_long', __( 'The title is too long.', 'niroroadmap' ), array( 'status' => 400 ) );
		}

		if ( strlen( (string) ( $input['description'] ?? '' ) ) > self::max_description() * 8 ) {
			return new \WP_Error( 'submission_description_long', __( 'The description is too long.', 'niroroadmap' ), array( 'status' => 400 ) );
		}

		$title       = trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) );
		$description = trim( sanitize_textarea_field( (string) ( $input['description'] ?? '' ) ) );

		if ( mb_strlen( $title ) < 3 ) {
			return new \WP_Error( 'submission_title', __( 'Please give your idea a title (at least 3 characters).', 'niroroadmap' ), array( 'status' => 400 ) );
		}

		if ( mb_strlen( $title ) > self::max_title() ) {
			/* translators: %d: maximum number of characters. */
			return new \WP_Error( 'submission_title_long', sprintf( __( 'The title can be at most %d characters long.', 'niroroadmap' ), self::max_title() ), array( 'status' => 400 ) );
		}

		if ( mb_strlen( $description ) > self::max_description() ) {
			/* translators: %d: maximum number of characters. */
			return new \WP_Error( 'submission_description_long', sprintf( __( 'The description can be at most %d characters long.', 'niroroadmap' ), self::max_description() ), array( 'status' => 400 ) );
		}

		// Who is suggesting it. Logged-in people are never asked: we already know.
		$name  = '';
		$email = '';

		if ( $logged_in ) {
			$user  = wp_get_current_user();
			$name  = $user->display_name;
			$email = $user->user_email;
		} elseif ( 'off' !== $identity ) {
			$name      = trim( sanitize_text_field( (string) ( $input['name'] ?? '' ) ) );
			$raw_email = trim( (string) ( $input['email'] ?? '' ) );
			$email     = sanitize_email( $raw_email );

			// sanitize_email() turns an invalid address into '', so check what was typed, not what's left.
			if ( '' !== $raw_email && ! is_email( $email ) ) {
				return new \WP_Error( 'submission_email', __( 'Please enter a valid email address.', 'niroroadmap' ), array( 'status' => 400 ) );
			}

			if ( 'required' === $identity && ( '' === $name || '' === $email ) ) {
				return new \WP_Error( 'submission_identity', __( 'Please enter your name and email.', 'niroroadmap' ), array( 'status' => 400 ) );
			}

			if ( mb_strlen( $name ) > 100 ) {
				return new \WP_Error( 'submission_name', __( 'That name is too long.', 'niroroadmap' ), array( 'status' => 400 ) );
			}
		}

		$product = absint( $input['product'] ?? 0 );
		if ( $product && ! term_exists( $product, 'niroroadmap_product' ) ) {
			return new \WP_Error( 'submission_product', __( 'Please choose a valid product.', 'niroroadmap' ), array( 'status' => 400 ) );
		}

		$id = wp_insert_post(
			array(
				'post_type'    => 'niroroadmap_item',
				'post_status'  => 'pending',
				'post_title'   => $title,
				'post_content' => $description,
				'post_author'  => $logged_in ? get_current_user_id() : 0,
				'meta_input'   => array(
					self::META_FLAG  => 1,
					self::META_NAME  => $name,
					self::META_EMAIL => $email,
				),
			),
			true
		);

		if ( is_wp_error( $id ) || ! $id ) {
			return new \WP_Error( 'submission_failed', __( 'Your idea could not be saved. Please try again.', 'niroroadmap' ), array( 'status' => 500 ) );
		}

		// Terms are set directly: assigning them through wp_insert_post() checks the current
		// user's capabilities, and a visitor has none.
		$status = (int) niroroadmap_get_setting( 'submissions_status' );
		if ( $status && term_exists( $status, 'niroroadmap_status' ) ) {
			wp_set_object_terms( $id, array( $status ), 'niroroadmap_status' );
		}

		if ( $product ) {
			wp_set_object_terms( $id, array( $product ), 'niroroadmap_product' );
		}

		self::auto_vote( $id );

		/**
		 * Fires after a visitor's idea was stored as a pending item.
		 *
		 * @param int $id The new item's ID.
		 */
		do_action( 'niroroadmap_submission_created', $id );

		self::notify( $id );

		return array( 'id' => (int) $id );
	}

	/**
	 * Count the submitter's own upvote, if the settings say so and they would be allowed to vote.
	 */
	private static function auto_vote( $id ) {
		if ( ! niroroadmap_get_setting( 'submissions_auto_vote' ) ) {
			return;
		}

		if ( 'logged_in' === niroroadmap_get_setting( 'vote_who' ) && ! is_user_logged_in() ) {
			return;
		}

		$voter = Voter::identify();

		if ( '' === $voter['hash'] ) {
			Voter::issue_cookie();
			$voter = Voter::identify();
		}

		// Goes through the same table as any vote, so the submitter can't vote on it again.
		Vote::cast( $id, 'upvote', $voter, false );
	}

	/**
	 * Tell the site owner a new idea is waiting.
	 */
	private static function notify( $id ) {
		if ( ! niroroadmap_get_setting( 'submissions_notify' ) ) {
			return;
		}

		$item = get_post( $id );
		$to   = niroroadmap_get_setting( 'submissions_email' );
		$to   = is_email( $to ) ? $to : get_option( 'admin_email' );

		/**
		 * Filters who is emailed about a new submission.
		 *
		 * @param string|string[] $to Recipient(s).
		 * @param int             $id Item ID.
		 */
		$to = apply_filters( 'niroroadmap_submission_notify_to', $to, $id );

		if ( ! $item || ! $to ) {
			return;
		}

		$name  = get_post_meta( $id, self::META_NAME, true );
		$email = get_post_meta( $id, self::META_EMAIL, true );
		$by    = $name || $email ? trim( $name . ( $email ? " <{$email}>" : '' ) ) : __( 'A visitor (no name given)', 'niroroadmap' );

		$subject = sprintf(
			/* translators: 1: site name, 2: idea title. */
			__( '[%1$s] New idea: %2$s', 'niroroadmap' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$item->post_title
		);

		$message = sprintf(
			/* translators: 1: title, 2: description, 3: who submitted it, 4: edit link. */
			__( "A new idea is waiting for your review.\n\nTitle: %1\$s\n\n%2\$s\n\nSubmitted by: %3\$s\n\nReview, edit or publish it here:\n%4\$s", 'niroroadmap' ),
			$item->post_title,
			$item->post_content ? $item->post_content : __( '(no description)', 'niroroadmap' ),
			$by,
			// Built by hand: get_edit_post_link() returns nothing unless the current user can edit the
			// item, and the current user here is the visitor who just submitted it.
			admin_url( 'post.php?post=' . (int) $id . '&action=edit' )
		);

		wp_mail( $to, $subject, $message );
	}

	/**
	 * Published items whose title or text matches, to steer people to an existing idea first.
	 *
	 * @param string $term Search text.
	 * @return array[] Up to five `id` / `title` pairs.
	 */
	public static function similar( $term ) {
		$term = trim( sanitize_text_field( $term ) );

		if ( mb_strlen( $term ) < 3 ) {
			return array();
		}

		$query = new \WP_Query(
			array(
				'post_type'           => 'niroroadmap_item',
				'post_status'         => 'publish',
				'has_password'        => false,
				's'                   => $term,
				'posts_per_page'      => 5,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		);

		return array_map(
			static function ( $post ) {
				return array(
					'id'    => (int) $post->ID,
					'title' => $post->post_title,
				);
			},
			$query->posts
		);
	}
}
