<?php
namespace NiroRoadmap\Model;

defined( 'ABSPATH' ) || exit;

/**
 * Who moved an item to which status, and when.
 *
 * Names are stored next to the term IDs so a row still reads properly if a status is later deleted.
 * The public timeline shows the status's current name and the date, never who moved it.
 */
class Status_Log {

	const SCHEMA_VERSION = '1';

	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'niroroadmap_status_log';
	}

	/**
	 * Create or update the table. Does nothing once it is up to date.
	 */
	public static function maybe_create_table() {
		if ( self::SCHEMA_VERSION === get_option( 'niroroadmap_status_log_schema' ) ) {
			return;
		}

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				item_id bigint(20) unsigned NOT NULL,
				from_term bigint(20) unsigned DEFAULT NULL,
				to_term bigint(20) unsigned DEFAULT NULL,
				from_name varchar(200) NOT NULL DEFAULT '',
				to_name varchar(200) NOT NULL DEFAULT '',
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY item_time (item_id,created_at)
			) {$charset};"
		);

		// Only mark it done if the table really exists, so a failed create is retried.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
		if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			update_option( 'niroroadmap_status_log_schema', self::SCHEMA_VERSION );
		}
	}

	/**
	 * Record a status change.
	 *
	 * @param int           $item_id Item ID.
	 * @param \WP_Term|null $from    The status it had, if any.
	 * @param \WP_Term|null $to      The status it has now, if any.
	 * @param int           $user_id Who did it. 0 for code and visitors.
	 */
	public static function record( $item_id, $from, $to, $user_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Our own table.
		$wpdb->insert(
			self::table(),
			array(
				'item_id'    => (int) $item_id,
				'from_term'  => $from ? (int) $from->term_id : null,
				'to_term'    => $to ? (int) $to->term_id : null,
				'from_name'  => $from ? mb_substr( $from->name, 0, 200 ) : '',
				'to_name'    => $to ? mb_substr( $to->name, 0, 200 ) : '',
				'user_id'    => (int) $user_id,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * The changes of an item.
	 *
	 * @param int    $item_id Item ID.
	 * @param string $order   `ASC` (oldest first) or `DESC`.
	 * @param int    $limit   Most rows to return.
	 * @return object[] Rows with id, from_term, to_term, from_name, to_name, user_id, created_at (UTC).
	 */
	public static function for_item( $item_id, $order = 'DESC', $limit = 50 ) {
		global $wpdb;

		$table = self::table();
		$order = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is ours and $order is one of two literals.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE item_id = %d ORDER BY created_at {$order}, id {$order} LIMIT %d", $item_id, max( 1, (int) $limit ) ) );
	}

	/**
	 * The path an item took, for the public popup: each status it was in and when it got there.
	 *
	 * Shows a status's current name (a renamed status reads as renamed). It never says who moved it.
	 *
	 * @param int $item_id Item ID.
	 * @return array[] `name` and `date` (a formatted date, or null for the first status, whose date
	 *                 isn't known), oldest first.
	 */
	public static function timeline( $item_id ) {
		$rows = self::for_item( $item_id, 'ASC', 100 );
		$out  = array();

		foreach ( $rows as $i => $row ) {
			// The very first row may start from a status the item already had before it was logged.
			if ( 0 === $i && $row->from_term ) {
				$out[] = array(
					'name' => self::name_of( $row->from_term, $row->from_name ),
					'date' => null,
				);
			}

			if ( $row->to_term ) {
				$out[] = array(
					'name' => self::name_of( $row->to_term, $row->to_name ),
					// A moment in time (stored in UTC), shown on the calendar of the site's own timezone.
					// Target and release are plain calendar dates, shown exactly as typed (Fields::display_date()).
					'date' => wp_date( get_option( 'date_format' ), strtotime( $row->created_at . ' UTC' ) ),
					'iso'  => gmdate( 'c', strtotime( $row->created_at . ' UTC' ) ),
				);
			}
		}

		return $out;
	}

	private static function name_of( $term_id, $snapshot ) {
		$term = get_term( (int) $term_id, 'niroroadmap_status' );

		return $term && ! is_wp_error( $term ) ? $term->name : $snapshot;
	}

	/**
	 * Remove the history of an item that was deleted for good.
	 */
	public static function delete_for_item( $item_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup.
		$wpdb->delete( self::table(), array( 'item_id' => (int) $item_id ), array( '%d' ) );
	}
}
