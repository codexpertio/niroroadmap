<?php
namespace NiroRoadmap\Model;

defined( 'ABSPATH' ) || exit;

/**
 * Comments on roadmap items. Native WordPress comments, so moderation, spam plugins (Akismet and
 * friends), the disallowed-words list and flood control all keep working. This class only decides
 * what the board may show and hands new comments to WordPress's own submission handler.
 */
class Comment {

	/**
	 * Whether the comments feature is turned on in Settings.
	 */
	public static function enabled() {
		return (bool) niroroadmap_get_setting( 'comments_enabled' );
	}

	/**
	 * Longest comment accepted, in characters.
	 */
	public static function max_length() {
		/**
		 * Filters the longest comment accepted from the roadmap board.
		 *
		 * @param int $length Characters. Default 2000.
		 */
		return max( 1, (int) apply_filters( 'niroroadmap_comment_max_length', 2000 ) );
	}

	/**
	 * What the current visitor may do on an item.
	 *
	 * @param \WP_Post $item Roadmap item.
	 * @return array
	 */
	public static function state( $item ) {
		$login_required = (bool) niroroadmap_get_setting( 'comments_login' ) || (bool) get_option( 'comment_registration' );
		$logged_in      = is_user_logged_in();
		$open           = comments_open( $item );

		return array(
			'open'               => $open,
			'login_required'     => $login_required,
			'logged_in'          => $logged_in,
			'require_name_email' => (bool) get_option( 'require_name_email' ),
			'can_comment'        => $open && ( ! $login_required || $logged_in ),
			'max_length'         => self::max_length(),
		);
	}

	/**
	 * A comment as the board needs it. Never includes the commenter's email or IP address.
	 *
	 * @param \WP_Comment $comment Comment.
	 * @param bool        $pending Whether it's the viewer's own comment awaiting moderation.
	 * @return array
	 */
	public static function format( $comment, $pending = false ) {
		$timestamp = strtotime( $comment->comment_date_gmt . ' UTC' );

		// Plain text in, so escape first; then link URLs the way WordPress does, as nofollow ugc.
		$html = wp_kses(
			wp_rel_ugc( wpautop( make_clickable( esc_html( $comment->comment_content ) ) ) ),
			array(
				'a'  => array(
					'href' => true,
					'rel'  => true,
				),
				'p'  => array(),
				'br' => array(),
			)
		);

		/**
		 * Filters whether comment avatars are shown on the roadmap board.
		 *
		 * An avatar URL carries a hash of the commenter's email address (Gravatar), the same as on
		 * any WordPress theme. Return false to leave avatars, and so those hashes, out entirely.
		 *
		 * @param bool $show Default: the Settings → Discussion "Show Avatars" option.
		 */
		$avatar = apply_filters( 'niroroadmap_comment_show_avatars', (bool) get_option( 'show_avatars' ) ) ? get_avatar_url( $comment, array( 'size' => 64 ) ) : '';

		return array(
			'id'         => (int) $comment->comment_ID,
			'parent'     => (int) $comment->comment_parent,
			'author'     => $comment->comment_author ? $comment->comment_author : __( 'Anonymous', 'niroroadmap' ),
			'avatar'     => $avatar ? $avatar : '',
			'content'    => $html,
			'date'       => gmdate( 'c', $timestamp ),
			/* translators: %s: time since the comment, e.g. "5 mins". */
			'date_human' => sprintf( __( '%s ago', 'niroroadmap' ), human_time_diff( $timestamp, time() ) ),
			'team'       => $comment->user_id && user_can( (int) $comment->user_id, 'edit_posts' ),
			'pending'    => $pending,
		);
	}

	/**
	 * One page of approved comments, top level first with their replies attached.
	 *
	 * @param \WP_Post $item     Roadmap item.
	 * @param int      $page     Page number, from 1.
	 * @param int      $per_page Top-level comments per page.
	 * @return array {
	 *     @type array[] $comments    Top-level comments, each with `replies`.
	 *     @type array[] $pending     The viewer's own comments awaiting moderation (first page only).
	 *     @type int     $count       All approved comments on the item.
	 *     @type int     $total_pages Pages of top-level comments.
	 *     @type int     $page        This page.
	 * }
	 */
	public static function page( $item, $page, $per_page ) {
		$page     = max( 1, (int) $page );
		$per_page = max( 1, min( 50, (int) $per_page ) );
		$base     = array(
			'post_id' => $item->ID,
			'type'    => 'comment',
			'status'  => 'approve',
			'parent'  => 0,
		);

		$total = (int) get_comments( $base + array( 'count' => true ) );
		$top   = get_comments(
			$base + array(
				'number'  => $per_page,
				'offset'  => ( $page - 1 ) * $per_page,
				'orderby' => 'comment_date_gmt',
				'order'   => 'newest' === niroroadmap_get_setting( 'comments_order' ) ? 'DESC' : 'ASC',
			)
		);

		$replies = array();
		if ( $top ) {
			$found = get_comments(
				array(
					'post_id'    => $item->ID,
					'type'       => 'comment',
					'status'     => 'approve',
					'parent__in' => wp_list_pluck( $top, 'comment_ID' ),
					'orderby'    => 'comment_date_gmt',
					'order'      => 'ASC',
				)
			);

			foreach ( $found as $reply ) {
				$replies[ (int) $reply->comment_parent ][] = self::format( $reply );
			}
		}

		$comments = array();
		foreach ( $top as $comment ) {
			$formatted            = self::format( $comment );
			$formatted['replies'] = $replies[ (int) $comment->comment_ID ] ?? array();
			$comments[]           = $formatted;
		}

		return array(
			'comments'    => $comments,
			'pending'     => 1 === $page ? self::own_pending( $item ) : array(),
			'count'       => (int) get_comments_number( $item->ID ),
			'total_pages' => (int) max( 1, ceil( $total / $per_page ) ),
			'page'        => $page,
		);
	}

	/**
	 * Comments the logged-in viewer wrote that are still awaiting moderation.
	 *
	 * Only the author ever sees these. An anonymous visitor has nothing that proves who wrote a
	 * comment, so they see theirs only in the response to the request that posted it.
	 *
	 * @return array[]
	 */
	private static function own_pending( $item ) {
		if ( ! is_user_logged_in() ) {
			return array();
		}

		$own = get_comments(
			array(
				'post_id' => $item->ID,
				'type'    => 'comment',
				'status'  => 'hold',
				'user_id' => get_current_user_id(),
				'orderby' => 'comment_date_gmt',
				'order'   => 'ASC',
			)
		);

		return array_map(
			static function ( $comment ) {
				return self::format( $comment, true );
			},
			$own
		);
	}

	/**
	 * Add a comment.
	 *
	 * Validation, moderation, duplicate and flood checks and spam hooks are WordPress's own
	 * (`wp_handle_comment_submission()`); this only adds the roadmap's rules on top.
	 *
	 * @param \WP_Post $item  Roadmap item.
	 * @param array    $input `content`, `parent`, `name`, `email`.
	 * @return array|\WP_Error `comment` (formatted), `pending` (bool), `count` (approved comments).
	 */
	public static function create( $item, $input ) {
		$state = self::state( $item );

		if ( ! $state['open'] ) {
			return new \WP_Error( 'comments_closed', __( 'Comments are closed for this item.', 'niroroadmap' ), array( 'status' => 403 ) );
		}

		if ( ! $state['can_comment'] ) {
			return new \WP_Error( 'comment_login_required', __( 'Please log in to comment.', 'niroroadmap' ), array( 'status' => 401 ) );
		}

		// Plain text only, whoever posts it.
		$content = trim( wp_strip_all_tags( (string) ( $input['content'] ?? '' ) ) );

		if ( '' === $content ) {
			return new \WP_Error( 'comment_empty', __( 'Please write a comment.', 'niroroadmap' ), array( 'status' => 400 ) );
		}

		if ( mb_strlen( $content ) > self::max_length() ) {
			/* translators: %d: maximum number of characters. */
			return new \WP_Error( 'comment_too_long', sprintf( __( 'Comments can be at most %d characters long.', 'niroroadmap' ), self::max_length() ), array( 'status' => 400 ) );
		}

		// One level of replies: the parent must be an approved top-level comment of this item.
		$parent_id = absint( $input['parent'] ?? 0 );
		if ( $parent_id ) {
			$parent = get_comment( $parent_id );

			if ( ! $parent || (int) $parent->comment_post_ID !== (int) $item->ID || '1' !== (string) $parent->comment_approved || $parent->comment_parent ) {
				return new \WP_Error( 'comment_bad_parent', __( 'You can’t reply to that comment.', 'niroroadmap' ), array( 'status' => 400 ) );
			}
		}

		$comment = wp_handle_comment_submission(
			array(
				'comment_post_ID' => $item->ID,
				'author'          => (string) ( $input['name'] ?? '' ),
				'email'           => (string) ( $input['email'] ?? '' ),
				'url'             => '',
				'comment'         => $content,
				'comment_parent'  => $parent_id,
			)
		);

		if ( is_wp_error( $comment ) ) {
			return $comment;
		}

		// Held, or marked as spam by a plugin: either way the visitor is told it awaits moderation.
		$pending = '1' !== (string) $comment->comment_approved;

		/**
		 * Fires after a comment was added from the roadmap board.
		 *
		 * @param \WP_Comment $comment The new comment.
		 * @param \WP_Post    $item    The roadmap item.
		 */
		do_action( 'niroroadmap_comment_created', $comment, $item );

		return array(
			'comment' => self::format( $comment, $pending ),
			'pending' => $pending,
			'count'   => (int) get_comments_number( $item->ID ),
		);
	}
}
