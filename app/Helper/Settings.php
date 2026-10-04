<?php
namespace NiroRoadmap\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings: one option array (`niroroadmap_settings`) described by a single schema.
 *
 * The schema drives the defaults, the typed getter, the server-side sanitizing and the admin
 * form, so a setting can't exist in one place and be forgotten in another.
 */
class Settings {

	const OPTION = 'niroroadmap_settings';

	const DEFAULT_ACCENT = '#2563eb';

	/**
	 * Settings tabs.
	 *
	 * @return array<string,string> Tab slug => label.
	 */
	public static function tabs() {
		return array(
			'general'    => __( 'General', 'niroroadmap' ),
			'voting'     => __( 'Voting', 'niroroadmap' ),
			'appearance' => __( 'Appearance', 'niroroadmap' ),
			'advanced'   => __( 'Advanced', 'niroroadmap' ),
		);
	}

	/**
	 * The settings schema.
	 *
	 * Keys per field: `tab`, `type` (checkbox|select|color|number|textarea), `label`,
	 * `description`, `default` (value or Closure), `options` (array or Closure, selects only),
	 * `cast` (`int` for selects holding IDs), `min` / `max` (numbers only).
	 *
	 * @return array<string,array>
	 */
	public static function fields() {
		static $fields = null;

		if ( null !== $fields ) {
			return $fields;
		}

		$fields = array(
			'roadmap_page'        => array(
				'tab'         => 'general',
				'type'        => 'select',
				'cast'        => 'int',
				'label'       => __( 'Roadmap page', 'niroroadmap' ),
				'description' => __( 'The page that shows your roadmap.', 'niroroadmap' ),
				// Installs from before the Settings page stored this in a hidden option.
				'default'     => function () {
					return (int) get_option( 'niroroadmap_page_id' );
				},
				'options'     => function () {
					$options = array( 0 => __( '— None —', 'niroroadmap' ) );

					foreach ( get_pages( array( 'post_status' => 'publish,private,draft' ) ) as $page ) {
						$options[ $page->ID ] = $page->post_title ? $page->post_title : sprintf( /* translators: %d: page ID. */ __( '(no title) #%d', 'niroroadmap' ), $page->ID );
					}

					return $options;
				},
			),
			'default_product'     => array(
				'tab'         => 'general',
				'type'        => 'select',
				'cast'        => 'int',
				'label'       => __( 'Default product', 'niroroadmap' ),
				'description' => __( 'The product shown on the board when the shortcode or block does not name one.', 'niroroadmap' ),
				'default'     => 0,
				'options'     => function () {
					$options = array( 0 => __( 'All products', 'niroroadmap' ) );
					$terms   = get_terms(
						array(
							'taxonomy'   => 'niroroadmap_product',
							'hide_empty' => false,
						)
					);

					if ( is_array( $terms ) ) {
						foreach ( $terms as $term ) {
							$options[ $term->term_id ] = $term->name;
						}
					}

					return $options;
				},
			),
			'stage_links'         => array(
				'tab'         => 'general',
				'type'        => 'checkbox',
				'label'       => __( 'Link status names to their archive page', 'niroroadmap' ),
				'description' => '',
				'default'     => false,
			),
			'show_vote_counts'    => array(
				'tab'         => 'general',
				'type'        => 'checkbox',
				'label'       => __( 'Show vote counts', 'niroroadmap' ),
				'description' => '',
				'default'     => true,
			),
			'show_tags'           => array(
				'tab'         => 'general',
				'type'        => 'checkbox',
				'label'       => __( 'Show tags', 'niroroadmap' ),
				'description' => '',
				'default'     => true,
			),
			'show_downvote'       => array(
				'tab'         => 'general',
				'type'        => 'checkbox',
				'label'       => __( 'Show the downvote button', 'niroroadmap' ),
				'description' => __( 'When off, downvotes are also rejected by the server.', 'niroroadmap' ),
				'default'     => true,
			),
			'vote_who'            => array(
				'tab'         => 'voting',
				'type'        => 'select',
				'label'       => __( 'Who can vote', 'niroroadmap' ),
				'description' => '',
				'default'     => 'everyone',
				'options'     => function () {
					return array(
						'everyone'  => __( 'Everyone', 'niroroadmap' ),
						'logged_in' => __( 'Logged-in users only', 'niroroadmap' ),
					);
				},
			),
			'show_downvotes_to'   => array(
				'tab'         => 'voting',
				'type'        => 'select',
				'label'       => __( 'Show downvote counts to', 'niroroadmap' ),
				'description' => '',
				'default'     => 'everyone',
				'options'     => function () {
					return array(
						'everyone' => __( 'Everyone', 'niroroadmap' ),
						'admins'   => __( 'Administrators only', 'niroroadmap' ),
					);
				},
			),
			'color_scheme'        => array(
				'tab'         => 'appearance',
				'type'        => 'select',
				'label'       => __( 'Color scheme', 'niroroadmap' ),
				'description' => __( '"Auto" follows the visitor\'s device setting.', 'niroroadmap' ),
				'default'     => 'light',
				'options'     => function () {
					return array(
						'light' => __( 'Light', 'niroroadmap' ),
						'dark'  => __( 'Dark', 'niroroadmap' ),
						'auto'  => __( 'Auto', 'niroroadmap' ),
					);
				},
			),
			'accent_color'        => array(
				'tab'         => 'appearance',
				'type'        => 'color',
				'label'       => __( 'Accent color', 'niroroadmap' ),
				'description' => '',
				'default'     => self::DEFAULT_ACCENT,
			),
			'card_radius'         => array(
				'tab'         => 'appearance',
				'type'        => 'number',
				'label'       => __( 'Corner radius (px)', 'niroroadmap' ),
				'description' => __( 'Between 0 and 32.', 'niroroadmap' ),
				'default'     => 12,
				'min'         => 0,
				'max'         => 32,
			),
			'custom_css'          => array(
				'tab'         => 'appearance',
				'type'        => 'textarea',
				'label'       => __( 'Custom CSS', 'niroroadmap' ),
				'description' => __( 'Loaded on pages that show the roadmap. HTML tags are removed.', 'niroroadmap' ),
				'default'     => '',
			),
			'delete_on_uninstall' => array(
				'tab'         => 'advanced',
				'type'        => 'checkbox',
				'label'       => __( 'Delete all data when the plugin is uninstalled', 'niroroadmap' ),
				'description' => __( 'Removes roadmap items (with their votes and comments), statuses, products, tags and these settings. The Roadmap page is kept. When off, everything is kept.', 'niroroadmap' ),
				'default'     => false,
			),
		);

		return $fields;
	}

	/**
	 * Default value of a setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed Null for an unknown key.
	 */
	public static function default_for( $key ) {
		$fields = self::fields();

		if ( ! isset( $fields[ $key ] ) ) {
			return null;
		}

		$default = $fields[ $key ]['default'];

		return $default instanceof \Closure ? self::cast( $fields[ $key ], $default() ) : $default;
	}

	/**
	 * Get a setting.
	 *
	 * The `niroroadmap_setting_{$key}` filter has the last word, so code can still override
	 * whatever the site owner picked.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Returned for a key that isn't in the schema.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$fields = self::fields();

		if ( ! isset( $fields[ $key ] ) ) {
			return $default;
		}

		$saved = get_option( self::OPTION, array() );
		$value = is_array( $saved ) && array_key_exists( $key, $saved ) ? $saved[ $key ] : self::default_for( $key );
		$value = self::cast( $fields[ $key ], $value );

		/**
		 * Filters a setting value.
		 *
		 * @param mixed  $value The setting value.
		 * @param string $key   The setting key.
		 */
		return apply_filters( "niroroadmap_setting_{$key}", $value, $key );
	}

	/**
	 * Save one setting, bypassing the form (used internally, e.g. by the installer).
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value New value. Must already be valid.
	 */
	public static function update( $key, $value ) {
		if ( ! isset( self::fields()[ $key ] ) ) {
			return;
		}

		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();

		$saved[ $key ] = $value;

		update_option( self::OPTION, $saved );
	}

	/**
	 * Cast a stored value to the type its field promises.
	 */
	private static function cast( $field, $value ) {
		if ( 'checkbox' === $field['type'] ) {
			return (bool) $value;
		}

		if ( 'number' === $field['type'] || 'int' === ( $field['cast'] ?? '' ) ) {
			return (int) $value;
		}

		return (string) $value;
	}

	/**
	 * Validate and sanitize a submitted settings form.
	 *
	 * Every field that is present gets validated, so the result is safe to store whatever the
	 * input was. A field that fails is rejected: it keeps its saved value and reports an error.
	 * Fields that are absent keep their saved value too, except unchecked checkboxes of the
	 * submitted tab (a browser sends nothing for those).
	 *
	 * WordPress can run this callback twice for one save (the first time the option is stored),
	 * the second time on the already-clean result, so it has to give the same answer on its own
	 * output.
	 *
	 * @param mixed $input The submitted `niroroadmap_settings` array.
	 * @return array The option value to store.
	 */
	public static function sanitize( $input ) {
		$current = get_option( self::OPTION, array() );
		$current = is_array( $current ) ? $current : array();
		$input   = is_array( $input ) ? $input : array();
		$tab     = isset( $input['_tab'] ) && is_string( $input['_tab'] ) ? sanitize_key( $input['_tab'] ) : '';

		$clean  = $current;
		$errors = 0;

		foreach ( self::fields() as $key => $field ) {
			if ( 'checkbox' === $field['type'] ) {
				if ( $field['tab'] === $tab || array_key_exists( $key, $input ) ) {
					$clean[ $key ] = ! empty( $input[ $key ] );
				}
				continue;
			}

			if ( ! isset( $input[ $key ] ) || ! is_scalar( $input[ $key ] ) ) {
				continue;
			}

			$value = self::sanitize_field( $field, (string) $input[ $key ] );

			if ( null === $value ) {
				++$errors;
				add_settings_error(
					self::OPTION,
					"niroroadmap_invalid_{$key}",
					/* translators: %s: setting label. */
					sprintf( __( 'Invalid value for "%s". It was not changed.', 'niroroadmap' ), $field['label'] ),
					'error'
				);
				continue;
			}

			$clean[ $key ] = $value;
		}

		// On the second pass nothing is invalid any more, so don't announce success next to the first pass's errors.
		if ( ! $errors && ! wp_list_filter( get_settings_errors( self::OPTION ), array( 'type' => 'error' ) ) ) {
			add_settings_error( self::OPTION, 'niroroadmap_saved', __( 'Settings saved.', 'niroroadmap' ), 'success' );
		}

		return $clean;
	}

	/**
	 * Sanitize one non-checkbox value.
	 *
	 * @return mixed The clean value, or null if it's invalid.
	 */
	private static function sanitize_field( $field, $value ) {
		switch ( $field['type'] ) {
			case 'select':
				$options = $field['options']();

				foreach ( array_keys( $options ) as $option ) {
					if ( (string) $option === $value ) {
						return 'int' === ( $field['cast'] ?? '' ) ? (int) $option : (string) $option;
					}
				}

				return null;

			case 'color':
				$color = sanitize_hex_color( $value );

				return $color ? $color : null;

			case 'number':
				if ( ! preg_match( '/^\d+$/', $value ) ) {
					return null;
				}

				$number = (int) $value;

				return $number >= $field['min'] && $number <= $field['max'] ? $number : null;

			case 'textarea':
				return trim( wp_strip_all_tags( $value ) );
		}

		return null;
	}

	/**
	 * CSS that applies the Appearance settings, to be added after the public stylesheet.
	 *
	 * @return string
	 */
	public static function inline_css() {
		$accent = sanitize_hex_color( self::get( 'accent_color' ) );
		$accent = $accent ? $accent : self::DEFAULT_ACCENT;
		$radius = max( 0, min( 32, (int) self::get( 'card_radius' ) ) );
		$scheme = self::get( 'color_scheme' );
		$root   = '.nr-kanban-columns,.nr-modal-overlay';
		$dark   = $root . '{--nr-bg:#111827;--nr-card:#1f2937;--nr-border:#374151;--nr-text:#f3f4f6;--nr-muted:#9ca3af}';

		$css = $root . '{--nr-accent:' . $accent . ';--nr-radius:' . $radius . 'px}';

		if ( 'dark' === $scheme ) {
			$css .= $dark;
		} elseif ( 'auto' === $scheme ) {
			$css .= '@media (prefers-color-scheme:dark){' . $dark . '}';
		}

		return $css . "\n" . self::get( 'custom_css' );
	}
}
