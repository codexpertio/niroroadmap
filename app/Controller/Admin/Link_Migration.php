<?php
namespace NiroRoadmap\Controller\Admin;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Bootstrap\Migrator;
use NiroRoadmap\Controller\Front\Legacy_Urls;
use NiroRoadmap\Traits\Hook;

/**
 * Tells the site owner the archive URLs moved, and updates old links in posts and widgets on request.
 */
class Link_Migration {

	use Hook;

	const BATCH = 100;

	const WIDGETS = array( 'widget_block', 'widget_text', 'widget_custom_html' );

	/**
	 * Constructor to add all hooks.
	 */
	public function __construct() {
		$this->action( 'admin_notices', array( $this, 'show_notice' ) );
		$this->action( 'admin_post_niroroadmap_dismiss_links_notice', array( $this, 'dismiss' ) );
		$this->action( 'admin_post_niroroadmap_update_links', array( $this, 'update_links' ) );
	}

	/**
	 * The Advanced settings tab, where the content tool lives.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	private static function tool_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'tab' => 'advanced' ), $args ), admin_url( 'edit.php?post_type=niroroadmap_item&page=' . Settings::SLUG ) ) . '#niroroadmap-links';
	}

	/**
	 * Once, after an update moved the archive URLs.
	 */
	public function show_notice() {
		$report = get_option( Migrator::REPORT );

		if ( ! is_array( $report ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=niroroadmap_dismiss_links_notice' ), 'niroroadmap_dismiss_links_notice' );
		?>
		<div class="notice notice-info">
			<p><strong><?php esc_html_e( 'NiroRoadmap: status, product and tag pages have new addresses.', 'niroroadmap' ); ?></strong></p>
			<p><?php esc_html_e( 'They now live under /roadmap-status/, /roadmap-product/ and /roadmap-tag/, so they no longer clash with shop plugins. Old addresses redirect to the new ones.', 'niroroadmap' ); ?></p>
			<p>
				<?php
				printf(
					/* translators: 1: number of menu links, 2: number of navigation menus and templates */
					esc_html__( 'Updated automatically: menu links %1$d, navigation menus and templates %2$d.', 'niroroadmap' ),
					(int) $report['menus'],
					(int) $report['navigation']
				);

				if ( ! empty( $report['skipped'] ) ) {
					echo ' ';
					printf(
						/* translators: %d: number of links */
						esc_html( _n( '%d link to /product-tag/ was left alone because a shop tag uses the same address; check it by hand.', '%d links to /product-tag/ were left alone because a shop tag uses the same address; check them by hand.', (int) $report['skipped'], 'niroroadmap' ) ),
						(int) $report['skipped']
					);
				}
				?>
			</p>
			<p><?php esc_html_e( 'Links typed into posts and widgets are not changed until you choose to. Afterwards, clear any page cache or CDN and let your SEO plugin rebuild its sitemap.', 'niroroadmap' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( self::tool_url() ); ?>"><?php esc_html_e( 'Update old links in posts', 'niroroadmap' ); ?></a>
				<a class="button" href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Dismiss', 'niroroadmap' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Hide the notice for good.
	 */
	public function dismiss() {
		check_admin_referer( 'niroroadmap_dismiss_links_notice' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'niroroadmap' ), '', array( 'response' => 403 ) );
		}

		delete_option( Migrator::REPORT );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Posts after `$after` whose content may hold an old roadmap archive URL.
	 *
	 * @param int $after Last post ID already handled.
	 * @param int $limit Max rows, or 0 to count them instead.
	 * @return array|int
	 */
	public static function find_posts( $after = 0, $limit = self::BATCH ) {
		global $wpdb;

		$like  = array();
		$where = array();

		foreach ( array_keys( Legacy_Urls::SLUGS ) as $base ) {
			$where[] = 'post_content LIKE %s';
			$like[]  = '%' . $wpdb->esc_like( '/' . $base . '/' ) . '%';
		}

		$sql = "FROM {$wpdb->posts} WHERE post_type NOT IN ( 'revision', 'nav_menu_item' ) AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' ) AND ID > %d AND ( " . implode( ' OR ', $where ) . ' )';

		if ( ! $limit ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$sql}", array_merge( array( (int) $after ), $like ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $sql holds only placeholders and the table name.
		}

		return $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_type {$sql} ORDER BY ID ASC LIMIT %d", array_merge( array( (int) $after ), $like, array( (int) $limit ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $sql holds only placeholders and the table name.
	}

	/**
	 * Swap old roadmap links in text, block, and custom HTML widgets.
	 *
	 * @param int $skipped Counts links left alone.
	 * @return int Widgets changed.
	 */
	public static function update_widgets( &$skipped = 0 ) {
		$changed = 0;

		foreach ( self::WIDGETS as $option ) {
			$widgets = get_option( $option );

			if ( ! is_array( $widgets ) ) {
				continue;
			}

			$before = $changed;

			foreach ( $widgets as $key => $widget ) {
				if ( ! is_array( $widget ) ) {
					continue;
				}

				foreach ( array( 'content', 'text' ) as $field ) {
					if ( isset( $widget[ $field ] ) && is_string( $widget[ $field ] ) ) {
						$new = Legacy_Urls::replace( $widget[ $field ], $skipped );

						if ( $new !== $widget[ $field ] ) {
							$widgets[ $key ][ $field ] = $new;
							++$changed;
						}
					}
				}
			}

			if ( $changed > $before ) {
				update_option( $option, $widgets );
			}
		}

		return $changed;
	}

	/**
	 * Update one batch of posts, and the widgets on the first batch.
	 */
	public function update_links() {
		check_admin_referer( 'niroroadmap_update_links' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'niroroadmap' ), '', array( 'response' => 403 ) );
		}

		$after   = isset( $_POST['after'] ) ? absint( $_POST['after'] ) : 0;
		$skipped = isset( $_POST['skipped'] ) ? absint( $_POST['skipped'] ) : 0;
		$changed = isset( $_POST['changed'] ) ? absint( $_POST['changed'] ) : 0;

		if ( ! $after ) {
			$changed += self::update_widgets( $skipped );
		}

		foreach ( self::find_posts( $after ) as $post ) {
			$after   = (int) $post->ID;
			$content = get_post_field( 'post_content', $after, 'raw' );
			$updated = Legacy_Urls::replace( $content, $skipped );

			if ( $updated !== $content ) {
				Migrator::update_content( $after, $updated );
				++$changed;
			}
		}

		$args = array(
			'niroroadmap-links' => self::find_posts( $after, 0 ) ? 'more' : 'done',
			'after'             => $after,
			'changed'           => $changed,
			'skipped'           => $skipped,
		);

		wp_safe_redirect( self::tool_url( $args ) );
		exit;
	}

	/**
	 * The "Update old roadmap links" section of the Advanced settings tab.
	 */
	public static function render_tool() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
		$state   = isset( $_GET['niroroadmap-links'] ) ? sanitize_key( $_GET['niroroadmap-links'] ) : '';
		$after   = 'more' === $state && isset( $_GET['after'] ) ? absint( $_GET['after'] ) : 0;
		$changed = isset( $_GET['changed'] ) ? absint( $_GET['changed'] ) : 0;
		$skipped = isset( $_GET['skipped'] ) ? absint( $_GET['skipped'] ) : 0;
		// phpcs:enable
		$total = self::find_posts( $after, 0 );
		$posts = self::find_posts( $after, 20 );
		?>
		<hr>
		<h2 id="niroroadmap-links"><?php esc_html_e( 'Update old roadmap links', 'niroroadmap' ); ?></h2>
		<p><?php esc_html_e( 'Status, product and tag pages moved from /product-cat/, /product-brand/ and /product-tag/ to /roadmap-status/, /roadmap-product/ and /roadmap-tag/. Old addresses redirect, but you can point links in posts, pages and widgets at the new ones. Each changed post keeps a revision.', 'niroroadmap' ); ?></p>

		<?php if ( $state ) : ?>
			<div class="notice notice-success inline"><p>
				<?php
				printf(
					/* translators: %d: number of posts and widgets */
					esc_html( _n( 'Updated %d post or widget.', 'Updated %d posts and widgets.', $changed, 'niroroadmap' ) ),
					(int) $changed
				);

				if ( $skipped ) {
					echo ' ';
					printf(
						/* translators: %d: number of links */
						esc_html( _n( '%d link to /product-tag/ was left alone because a shop tag uses the same address.', '%d links to /product-tag/ were left alone because a shop tag uses the same address.', $skipped, 'niroroadmap' ) ),
						(int) $skipped
					);
				}
				?>
			</p></div>
		<?php endif; ?>

		<?php if ( $total ) : ?>
			<p>
				<?php
				printf(
					/* translators: %d: number of posts */
					esc_html( _n( '%d post may still hold old links:', '%d posts may still hold old links:', $total, 'niroroadmap' ) ),
					(int) $total
				);
				?>
			</p>
			<ul class="ul-disc">
				<?php foreach ( $posts as $post ) : ?>
					<li><a href="<?php echo esc_url( (string) get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( '' !== $post->post_title ? $post->post_title : __( '(no title)', 'niroroadmap' ) ); ?></a> (<?php echo esc_html( $post->post_type ); ?>)</li>
				<?php endforeach; ?>
			</ul>
		<?php elseif ( ! $state ) : ?>
			<p><?php esc_html_e( 'No posts hold old links. The button still checks your widgets.', 'niroroadmap' ); ?></p>
		<?php endif; ?>

		<?php if ( 'done' !== $state || $total ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="niroroadmap_update_links">
				<input type="hidden" name="after" value="<?php echo esc_attr( $after ); ?>">
				<input type="hidden" name="changed" value="<?php echo esc_attr( $changed ); ?>">
				<input type="hidden" name="skipped" value="<?php echo esc_attr( $skipped ); ?>">
				<?php wp_nonce_field( 'niroroadmap_update_links' ); ?>
				<?php submit_button( 'more' === $state ? __( 'Continue updating links', 'niroroadmap' ) : __( 'Update old links', 'niroroadmap' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php
	}
}
