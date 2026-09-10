<?php
namespace NiroRoadmap\Trait;

defined( 'ABSPATH' ) || exit;

/**
 * Trait Cleaner
 *
 * This trait provides methods for sanitizing and escaping input and output data in the WordPress plugin.
 *
 * @package NiroRoadmap
 */
trait Cleaner {

	/**
	 * Generic sanitization method that dynamically selects the appropriate sanitization function based on the type or data content.
	 *
	 * @param mixed  $input The input data to sanitize, can be a string or an array.
	 * @param string $type  The type of sanitization to perform, or 'array' to recursively sanitize an array of data.
	 * @return mixed The sanitized data, either a string or an array of sanitized strings.
	 */
	public function sanitize( $input, $type = 'text' ) {
		if ( is_array( $input ) ) {
			$sanitized = array();
			foreach ( $input as $key => $value ) {
				if ( is_array( $value ) ) {
					$sanitized[ $key ] = $this->sanitize( $value, 'array' ); // Recursive sanitization for nested arrays
				} elseif ( ! is_null( $value ) ) {
					// Determine type based on content (handling line breaks for textarea)
					$_type             = ( strpos( $value, PHP_EOL ) !== false ) ? 'textarea' : 'text';
					$sanitized[ $key ] = $this->sanitize( $value, $_type );
				}
			}
			return $sanitized;
		}

		switch ( $type ) {
			case 'username':
				return sanitize_user( $input, true );
			case 'text':
				return sanitize_text_field( $input );
			case 'textarea':
				return sanitize_textarea_field( $input );
			case 'email':
				return sanitize_email( $input );
			case 'url':
				return esc_url_raw( $input );
			case 'title':
				return sanitize_title( $input );
			case 'key':
				return sanitize_key( $input );
			default:
				return $input; // Return input as is if type is not recognized
		}
	}

	/**
	 * Generic escaping method for securing output against XSS and other vulnerabilities.
	 * If an array is provided as output, the specified escaping context will be applied to each element.
	 *
	 * @param mixed  $output The output data to escape, can be a string or an array of strings.
	 * @param string $context The context for escaping. Default is 'html'.
	 *                        Supported contexts: 'html', 'url', 'js', 'sql', 'attr'.
	 * @return mixed The escaped data, either a string or an array of escaped strings.
	 */
	public function escape( $output, $context = 'html' ) {
		if ( is_array( $output ) ) {
			return array_map(
				function ( $item ) use ( $context ) {
					return $this->escape( $item, $context );
				},
				$output
			);
		}

		switch ( $context ) {
			case 'html':
				return esc_html( $output );
			case 'url':
				return esc_url( $output );
			case 'js':
				return esc_js( $output );
			case 'sql':
				global $wpdb;
				return $wpdb->_escape( $output );
			case 'attr':
				return esc_attr( $output );
			default:
				return $output;  // Return the output as is if the context is not supported.
		}
	}
}
