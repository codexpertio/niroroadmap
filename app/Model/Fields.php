<?php
namespace NiroRoadmap\Model;

defined( 'ABSPATH' ) || exit;

/**
 * The structured fields of a roadmap item: when it is planned and shipped, how big it is, an
 * outside link, and two switches (hide its votes, pin it to the top of its column).
 *
 * All are protected post meta (underscore prefix), so they stay out of the Custom Fields box. None
 * is registered for core's REST API on purpose: registered meta shows up in the public `meta`
 * field of a published item, and some of these must never be public.
 */
class Fields {

	const TARGET      = '_niroroadmap_target';
	const TARGET_SORT = '_niroroadmap_target_sort';
	const RELEASE     = '_niroroadmap_release_date';
	const VERSION     = '_niroroadmap_version';
	const EFFORT      = '_niroroadmap_effort';
	const NOTES       = '_niroroadmap_notes';
	const LINK        = '_niroroadmap_link';
	const HIDE_VOTES  = '_niroroadmap_hide_votes';
	const PINNED      = '_niroroadmap_pinned';

	const MAX_VERSION = 40;
	const MAX_NOTES   = 5000;

	/**
	 * T-shirt sizes.
	 *
	 * @return array<string,string> Value => label.
	 */
	public static function efforts() {
		return array(
			'XS' => __( 'XS – a few hours', 'niroroadmap' ),
			'S'  => __( 'S – a day or two', 'niroroadmap' ),
			'M'  => __( 'M – about a week', 'niroroadmap' ),
			'L'  => __( 'L – a few weeks', 'niroroadmap' ),
			'XL' => __( 'XL – a quarter or more', 'niroroadmap' ),
		);
	}

	/**
	 * Understand a target: a date (2026-12-31) or a quarter (Q4 2026).
	 *
	 * @param string $value What was typed.
	 * @return array|null `text` (how it is stored and shown) and `sort` (a date to order by; a
	 *                    quarter sorts as its first day). Both empty for an empty value. Null if
	 *                    it is neither a date nor a quarter.
	 */
	public static function parse_target( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return array(
				'text' => '',
				'sort' => '',
			);
		}

		if ( self::is_date( $value ) ) {
			return array(
				'text' => $value,
				'sort' => $value,
			);
		}

		if ( preg_match( '/^q([1-4])[\s\-]*(\d{4})$/i', $value, $m ) && (int) $m[2] >= 2000 && (int) $m[2] <= 2100 ) {
			return array(
				'text' => 'Q' . $m[1] . ' ' . $m[2],
				'sort' => sprintf( '%04d-%02d-01', $m[2], ( (int) $m[1] - 1 ) * 3 + 1 ),
			);
		}

		return null;
	}

	/**
	 * Whether a string is a real calendar date written YYYY-MM-DD.
	 */
	public static function is_date( $value ) {
		return (bool) preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * Validate what an editor submitted.
	 *
	 * A field that fails is left out of `values` (so it keeps its saved value) and described in
	 * `errors`.
	 *
	 * @param array $input Raw form values, by short name: target, release, version, effort, notes,
	 *                     link, hide_votes, pinned.
	 * @return array {
	 *     @type array $values Meta key => clean value. An empty string means "remove it".
	 *     @type array $errors Messages.
	 * }
	 */
	public static function sanitize( $input ) {
		$values = array();
		$errors = array();

		if ( isset( $input['target'] ) ) {
			$target = self::parse_target( wp_unslash( (string) $input['target'] ) );

			if ( null === $target ) {
				$errors[] = __( 'The target must be a date like 2026-12-31 or a quarter like Q4 2026. It was not changed.', 'niroroadmap' );
			} else {
				$values[ self::TARGET ]      = $target['text'];
				$values[ self::TARGET_SORT ] = $target['sort'];
			}
		}

		if ( isset( $input['release'] ) ) {
			$release = trim( wp_unslash( (string) $input['release'] ) );

			if ( '' !== $release && ! self::is_date( $release ) ) {
				$errors[] = __( 'The release date must be a date like 2026-12-31. It was not changed.', 'niroroadmap' );
			} else {
				$values[ self::RELEASE ] = $release;
			}
		}

		if ( isset( $input['version'] ) ) {
			$version = trim( sanitize_text_field( wp_unslash( (string) $input['version'] ) ) );

			if ( mb_strlen( $version ) > self::MAX_VERSION ) {
				/* translators: %d: maximum number of characters. */
				$errors[] = sprintf( __( 'The version can be at most %d characters. It was not changed.', 'niroroadmap' ), self::MAX_VERSION );
			} else {
				$values[ self::VERSION ] = $version;
			}
		}

		if ( isset( $input['effort'] ) ) {
			$effort = (string) $input['effort'];

			if ( '' !== $effort && ! isset( self::efforts()[ $effort ] ) ) {
				$errors[] = __( 'That effort size is not one of the choices. It was not changed.', 'niroroadmap' );
			} else {
				$values[ self::EFFORT ] = $effort;
			}
		}

		if ( isset( $input['notes'] ) ) {
			$notes = trim( sanitize_textarea_field( wp_unslash( (string) $input['notes'] ) ) );

			if ( mb_strlen( $notes ) > self::MAX_NOTES ) {
				/* translators: %d: maximum number of characters. */
				$errors[] = sprintf( __( 'The internal notes can be at most %d characters. They were not changed.', 'niroroadmap' ), self::MAX_NOTES );
			} else {
				$values[ self::NOTES ] = $notes;
			}
		}

		if ( isset( $input['link'] ) ) {
			$raw  = trim( wp_unslash( (string) $input['link'] ) );
			$link = self::clean_link( $raw );

			if ( '' !== $raw && '' === $link ) {
				$errors[] = __( 'The link must be a web address starting with http:// or https://. It was not changed.', 'niroroadmap' );
			} else {
				$values[ self::LINK ] = $link;
			}
		}

		// Checkboxes send nothing when off, so their keys are always present in a real submission.
		foreach ( array(
			'hide_votes' => self::HIDE_VOTES,
			'pinned'     => self::PINNED,
		) as $name => $key ) {
			if ( array_key_exists( $name, $input ) ) {
				$values[ $key ] = empty( $input[ $name ] ) || '0' === $input[ $name ] ? '' : '1';
			}
		}

		return array(
			'values' => $values,
			'errors' => $errors,
		);
	}

	/**
	 * Store a set of clean values: an empty one removes the meta, so items stay tidy.
	 *
	 * @param int   $post_id Item ID.
	 * @param array $values  Meta key => value, from sanitize().
	 */
	public static function save( $post_id, $values ) {
		foreach ( $values as $key => $value ) {
			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	/**
	 * Register the meta so core validates writes from any code path, and only editors of the item may write.
	 *
	 * Deliberately without `show_in_rest`; see the class comment.
	 */
	public static function register() {
		$auth = static function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};

		foreach ( array( self::TARGET, self::TARGET_SORT, self::RELEASE, self::VERSION, self::EFFORT, self::NOTES, self::LINK, self::HIDE_VOTES, self::PINNED ) as $key ) {
			register_post_meta(
				'niroroadmap_item',
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'auth_callback'     => $auth,
					'sanitize_callback' => static function ( $value ) use ( $key ) {
						return self::sanitize_one( $key, $value );
					},
				)
			);
		}
	}

	/**
	 * A web address, or '' if it isn't one.
	 *
	 * The scheme has to be typed. esc_url_raw() on its own would turn the words "not a url" into
	 * http://notaurl, which is not what the editor meant.
	 *
	 * @param string $value What was typed.
	 * @return string
	 */
	public static function clean_link( $value ) {
		$value = trim( (string) $value );

		if ( ! preg_match( '#^https?://[^\s/$.?\#].[^\s]*$#i', $value ) ) {
			return '';
		}

		return esc_url_raw( $value, array( 'http', 'https' ) );
	}

	/**
	 * Clean one value for storage when it arrives through update_post_meta() directly.
	 * Invalid values become empty rather than being stored.
	 */
	private static function sanitize_one( $key, $value ) {
		$value = (string) $value;

		switch ( $key ) {
			case self::TARGET:
				$target = self::parse_target( $value );

				return $target ? $target['text'] : '';

			case self::TARGET_SORT:
			case self::RELEASE:
				return self::is_date( $value ) ? $value : '';

			case self::VERSION:
				return mb_substr( trim( sanitize_text_field( $value ) ), 0, self::MAX_VERSION );

			case self::EFFORT:
				return isset( self::efforts()[ $value ] ) ? $value : '';

			case self::NOTES:
				return mb_substr( trim( sanitize_textarea_field( $value ) ), 0, self::MAX_NOTES );

			case self::LINK:
				return self::clean_link( $value );

			default:
				return '1' === $value ? '1' : '';
		}
	}

	/**
	 * Whether an item has its vote counts switched off.
	 */
	public static function votes_hidden( $post_id ) {
		return '1' === get_post_meta( $post_id, self::HIDE_VOTES, true );
	}

	/**
	 * Whether an item is pinned to the top of its column.
	 */
	public static function is_pinned( $post_id ) {
		return '1' === get_post_meta( $post_id, self::PINNED, true );
	}

	/**
	 * Show a stored date the way the site writes dates. A quarter is shown as it was typed.
	 *
	 * @param string $value A date (YYYY-MM-DD) or a quarter.
	 */
	public static function display_date( $value ) {
		if ( self::is_date( $value ) ) {
			return wp_date( get_option( 'date_format' ), strtotime( $value . ' 12:00:00 UTC' ), new \DateTimeZone( 'UTC' ) );
		}

		return (string) $value;
	}

	/**
	 * What the public popup may show about an item beyond its title and text.
	 *
	 * Internal fields (effort, notes) are never in here. The target needs its setting.
	 *
	 * @param \WP_Post $item Published item.
	 * @return array `cover`, `target`, `release`, `version`, `link`: each only when set.
	 */
	public static function public_facts( $item ) {
		$facts = array();
		$id    = $item->ID;

		if ( has_post_thumbnail( $id ) ) {
			$thumb_id = get_post_thumbnail_id( $id );
			$url      = wp_get_attachment_image_url( $thumb_id, 'large' );

			if ( $url ) {
				$facts['cover'] = array(
					'url' => $url,
					'alt' => (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
				);
			}
		}

		$target = (string) get_post_meta( $id, self::TARGET, true );
		if ( '' !== $target && niroroadmap_get_setting( 'show_target' ) ) {
			$facts['target'] = self::display_date( $target );
		}

		$release = (string) get_post_meta( $id, self::RELEASE, true );
		if ( self::is_date( $release ) ) {
			$facts['release'] = self::display_date( $release );
		}

		$version = (string) get_post_meta( $id, self::VERSION, true );
		if ( '' !== $version ) {
			$facts['version'] = $version;
		}

		$link = (string) get_post_meta( $id, self::LINK, true );
		if ( '' !== $link ) {
			$facts['link'] = self::clean_link( $link );
		}

		return array_filter(
			$facts,
			static function ( $fact ) {
				return ! empty( $fact );
			}
		);
	}
}
