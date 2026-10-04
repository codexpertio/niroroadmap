<?php
namespace NiroRoadmap\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Identifies who is voting, without storing anything that identifies a person in the clear.
 *
 * - Logged-in users are identified by user ID, so their vote follows them across devices.
 * - Anonymous visitors get a random token in a first-party cookie.
 * - Anonymous visitors also get a salted hash of IP + user agent, so clearing cookies doesn't
 *   reset them. The raw IP and user agent are never stored.
 */
class Voter {

	const COOKIE = 'niroroadmap_voter';

	/**
	 * Who is making this request.
	 *
	 * @return array {
	 *     @type string $hash        Stable per-voter hash: user ID, or the cookie token. Empty for an
	 *                               anonymous visitor who has no cookie yet.
	 *     @type string $fingerprint Salted IP + user agent hash. Empty for logged-in users, or when
	 *                               the `niroroadmap_vote_fingerprint` filter turned it off.
	 *     @type string $token       The anonymous visitor's cookie token. Empty for logged-in users.
	 * }
	 */
	public static function identify() {
		if ( is_user_logged_in() ) {
			return array(
				'hash'        => self::hash( 'user|' . get_current_user_id() ),
				'fingerprint' => '',
				'token'       => '',
			);
		}

		$token = self::cookie_token();

		return array(
			'hash'        => $token ? self::hash( 'token|' . $token ) : '',
			'fingerprint' => self::fingerprint(),
			'token'       => $token,
		);
	}

	/**
	 * Hash of the visitor's IP address. The key for rate limiting. Empty if the IP is unknown.
	 *
	 * @return string
	 */
	public static function ip_hash() {
		$ip = self::ip();

		return $ip ? self::hash( 'ip|' . $ip ) : '';
	}

	/**
	 * Give an anonymous visitor a voter cookie. Call before any output is sent.
	 *
	 * @param string $token Existing token to refresh, or empty to make a new one.
	 * @return string The token in use, which `identify()` will pick up from now on.
	 */
	public static function issue_cookie( $token = '' ) {
		$token = $token ? $token : bin2hex( random_bytes( 16 ) );

		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE,
				$token,
				array(
					'expires'  => time() + YEAR_IN_SECONDS,
					'path'     => '/',
					'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}

		// So the rest of this request sees it too.
		$_COOKIE[ self::COOKIE ] = $token;

		return $token;
	}

	/**
	 * The token from the voter cookie, if there is a well-formed one.
	 */
	private static function cookie_token() {
		$token = isset( $_COOKIE[ self::COOKIE ] ) && is_string( $_COOKIE[ self::COOKIE ] ) ? wp_unslash( $_COOKIE[ self::COOKIE ] ) : '';

		return preg_match( '/^[a-f0-9]{32}$/', $token ) ? $token : '';
	}

	/**
	 * Salted hash of IP + user agent.
	 */
	private static function fingerprint() {
		$ip         = self::ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '';
		$fingerprint = ( $ip || $user_agent ) ? self::hash( 'fp|' . $ip . '|' . $user_agent ) : '';

		/**
		 * Filters the anonymous voter fingerprint (a salted hash of IP + user agent).
		 *
		 * Return an empty string to turn this layer off, e.g. when a site prefers not to process
		 * IP addresses at all. Votes are then de-duplicated by cookie only.
		 *
		 * @param string $fingerprint The hash, or empty.
		 * @param string $ip          The visitor's IP address, or empty.
		 * @param string $user_agent  The visitor's user agent, or empty.
		 */
		$fingerprint = apply_filters( 'niroroadmap_vote_fingerprint', $fingerprint, $ip, $user_agent );

		return is_string( $fingerprint ) && preg_match( '/^[a-f0-9]{64}$/', $fingerprint ) ? $fingerprint : '';
	}

	/**
	 * The visitor's IP address.
	 *
	 * Only `REMOTE_ADDR` is trusted. Forwarded-for headers are set by the client and would let
	 * anyone dodge the limits. Sites behind a proxy can supply the real address with the
	 * `niroroadmap_voter_ip` filter.
	 */
	private static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filters the IP address used to identify a voter.
		 *
		 * @param string $ip The address from `REMOTE_ADDR`.
		 */
		$ip = apply_filters( 'niroroadmap_voter_ip', $ip );

		return is_string( $ip ) && filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	private static function hash( $value ) {
		return hash_hmac( 'sha256', $value, wp_salt( 'auth' ) );
	}
}
