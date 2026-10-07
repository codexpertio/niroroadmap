<?php
namespace NiroRoadmap\Controller\Admin;

defined( 'ABSPATH' ) || exit;

use NiroRoadmap\Trait\Hook;
use NiroRoadmap\Trait\Menu;
use NiroRoadmap\Trait\Asset as Asset_Trait;
use NiroRoadmap\Helper\Settings as Settings_Helper;
use NiroRoadmap\Helper\Sample_Data;

/**
 * The onboarding screen: a short checklist that gets a new site from "just activated" to a
 * working public roadmap, plus where to find help.
 */
class Getting_Started {

	use Hook;
	use Menu;
	use Asset_Trait;

	const SLUG     = 'niroroadmap-getting-started';
	const REDIRECT = 'niroroadmap_activation_redirect';
	const DONE     = 'niroroadmap_getting_started_done';

	/**
	 * Constructor to add all hooks.
	 */
	public function __construct() {
		$this->action( 'admin_menu', array( $this, 'add_menu_page' ) );
		$this->action( 'admin_menu', array( $this, 'maybe_hide_menu' ), 99 );
		$this->action( 'admin_notices', array( $this, 'show_help_notice' ) );
		$this->action( 'admin_init', array( $this, 'maybe_redirect' ) );
		$this->action( 'admin_enqueue_scripts', array( $this, 'add_assets' ) );
		$this->action( 'admin_post_niroroadmap_sample_items', array( $this, 'handle_sample_items' ) );
		$this->filter( 'plugin_action_links_' . plugin_basename( NIROROADMAP_FILE ), array( $this, 'add_action_link' ) );
		$this->filter( 'plugin_row_meta', array( $this, 'add_row_meta' ), 10, 2 );
	}

	public function add_menu_page() {
		$hook = $this->add_submenu(
			'edit.php?post_type=niroroadmap_item',
			__( 'Getting Started with NiroRoadmap', 'niroroadmap' ),
			__( 'Getting Started', 'niroroadmap' ),
			'edit_pages',
			self::SLUG,
			array( $this, 'render' )
		);

		if ( $hook ) {
			$this->action( 'load-' . $hook, array( $this, 'set_page_title' ) );
		}
	}

	/**
	 * WordPress finds a page's title in the menu. With the entry hidden there is none, which leaves
	 * the title null and triggers a deprecation notice in the admin header.
	 */
	public function set_page_title() {
		global $title;

		if ( empty( $title ) ) {
			$title = __( 'Getting Started with NiroRoadmap', 'niroroadmap' );
		}
	}

	/**
	 * Once every step on the screen is done, drop it from the menu. The page itself stays registered,
	 * so the Plugins list and Settings links still reach it.
	 */
	public function maybe_hide_menu() {
		if ( get_option( self::DONE ) ) {
			remove_submenu_page( 'edit.php?post_type=niroroadmap_item', self::SLUG );
		}
	}

	/**
	 * A reminder on every NiroRoadmap screen until the checklist is finished.
	 */
	public function show_help_notice() {
		$screen = get_current_screen();

		if (
			! $screen ||
			get_option( self::DONE ) ||
			false !== strpos( $screen->id, self::SLUG ) ||
			! current_user_can( 'edit_pages' ) ||
			! ( 'niroroadmap_item' === $screen->post_type || 0 === strpos( (string) $screen->taxonomy, 'niroroadmap_' ) )
		) {
			return;
		}

		// A site that was already set up before this screen existed has nothing left to be told.
		if ( ! in_array( false, wp_list_pluck( $this->steps(), 'done' ), true ) ) {
			update_option( self::DONE, 1 );
			return;
		}
		?>
		<div class="notice notice-info">
			<p>
				<strong><?php esc_html_e( 'New here?', 'niroroadmap' ); ?></strong>
				<a href="<?php echo esc_url( self::page_url() ); ?>"><?php esc_html_e( 'Follow the Getting Started guide', 'niroroadmap' ); ?></a>
				&middot;
				<a href="<?php echo esc_url( self::docs_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Visit our website', 'niroroadmap' ); ?></a>
			</p>
		</div>
		<?php
	}

	public function add_assets( $hook ) {
		if ( false === strpos( $hook, self::SLUG ) ) {
			return;
		}

		$this->enqueue_style( 'niroroadmap-getting-started', NIROROADMAP_ASSETS_URL . 'admin/css/getting-started.css' );
	}

	/**
	 * Where visitors of the docs and the support forum land. Filterable so a site can point elsewhere.
	 */
	public static function docs_url() {
		return apply_filters( 'niroroadmap_docs_url', 'https://nirosuite.com/niroroadmap' );
	}

	public static function support_url() {
		return apply_filters( 'niroroadmap_support_url', 'https://wordpress.org/support/plugin/niroroadmap/' );
	}

	public static function page_url() {
		return add_query_arg(
			array(
				'post_type' => 'niroroadmap_item',
				'page'      => self::SLUG,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Send the person who just activated the plugin to this screen, once.
	 */
	public function maybe_redirect() {
		if ( ! get_transient( self::REDIRECT ) ) {
			return;
		}

		delete_transient( self::REDIRECT );

		// Bulk activation, AJAX, network admin and people who can't see the screen are left alone.
		if (
			wp_doing_ajax() ||
			is_network_admin() ||
			isset( $_GET['activate-multi'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads a WordPress flag.
			! current_user_can( 'edit_pages' )
		) {
			return;
		}

		wp_safe_redirect( self::page_url() );
		exit;
	}

	public function add_action_link( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( self::page_url() ), esc_html__( 'Getting Started', 'niroroadmap' ) )
		);

		return $links;
	}

	public function add_row_meta( $meta, $file ) {
		if ( plugin_basename( NIROROADMAP_FILE ) !== $file ) {
			return $meta;
		}

		$meta[] = sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( self::docs_url() ), esc_html__( 'Documentation', 'niroroadmap' ) );
		$meta[] = sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( self::support_url() ), esc_html__( 'Support', 'niroroadmap' ) );

		return $meta;
	}

	/**
	 * Add or remove the example items.
	 */
	public function handle_sample_items() {
		check_admin_referer( 'niroroadmap_sample_items' );

		if ( ! current_user_can( 'publish_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'niroroadmap' ), '', array( 'response' => 403 ) );
		}

		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';

		if ( 'remove' === $do ) {
			Sample_Data::remove();
		} elseif ( 'add' === $do ) {
			Sample_Data::create();
		}

		wp_safe_redirect( add_query_arg( 'niroroadmap-sample', $do, self::page_url() ) );
		exit;
	}

	/**
	 * What's done so far, worked out from the site itself so the checklist never needs resetting.
	 */
	private function steps() {
		// Example items don't count: the checklist is about the owner's own roadmap.
		$items    = max( 0, (int) wp_count_posts( 'niroroadmap_item' )->publish - count( Sample_Data::item_ids() ) );
		$statuses = (int) wp_count_terms( array( 'taxonomy' => 'niroroadmap_status', 'hide_empty' => false ) );
		$page_id  = (int) niroroadmap_get_setting( 'roadmap_page' );
		$page_url = $page_id && 'publish' === get_post_status( $page_id ) ? get_permalink( $page_id ) : '';
		$saved    = get_option( Settings_Helper::OPTION );
		// The installer saves the Roadmap page, so that one doesn't count as the owner choosing something.
		$saved    = is_array( $saved ) ? array_diff_key( $saved, array( 'roadmap_page' => 1 ) ) : array();

		return array(
			array(
				'title' => __( 'Set up your columns', 'niroroadmap' ),
				'text'  => __( 'Statuses are the columns of your board, such as Planned, In Progress and Completed. We added four for you. Rename them, pick colors and drag them into order.', 'niroroadmap' ),
				'done'  => $statuses > 0,
				'url'   => admin_url( 'edit-tags.php?taxonomy=niroroadmap_status&post_type=niroroadmap_item' ),
				'label' => __( 'Edit statuses', 'niroroadmap' ),
			),
			array(
				'title' => __( 'Add your first item', 'niroroadmap' ),
				'text'  => __( 'An item is one thing you are planning or building. Give it a title, a short description and a status. Everything else is optional.', 'niroroadmap' ),
				'done'  => $items > 0,
				'url'   => admin_url( 'post-new.php?post_type=niroroadmap_item' ),
				'label' => __( 'Add an item', 'niroroadmap' ),
			),
			array(
				'title' => __( 'See your public roadmap', 'niroroadmap' ),
				'text'  => $page_url
					? __( 'We created a Roadmap page for you. This is what your visitors see.', 'niroroadmap' )
					: __( 'Create a page and add the Roadmap block or the [niroroadmap] shortcode. Then choose it under Settings → General → Roadmap page.', 'niroroadmap' ),
				'done'  => $items > 0 && $page_url,
				'url'   => $page_url ? $page_url : admin_url( 'post-new.php?post_type=page' ),
				'label' => $page_url ? __( 'View roadmap', 'niroroadmap' ) : __( 'Create a page', 'niroroadmap' ),
				'blank' => (bool) $page_url,
			),
			array(
				'title' => __( 'Choose what visitors can do', 'niroroadmap' ),
				'text'  => __( 'Voting is on by default. Turn on comments, the Suggest an idea button, a search toolbar, or the List and Timeline views under Settings.', 'niroroadmap' ),
				'done'  => ! empty( $saved ),
				'url'   => add_query_arg(
					array(
						'post_type' => 'niroroadmap_item',
						'page'      => Settings::SLUG,
					),
					admin_url( 'edit.php' )
				),
				'label' => __( 'Open settings', 'niroroadmap' ),
			),
		);
	}

	public function render() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}

		$steps     = $this->steps();
		$completed = count( array_filter( wp_list_pluck( $steps, 'done' ) ) );

		// Remembered, so the menu entry doesn't come back if an item is deleted later.
		if ( count( $steps ) === $completed && ! get_option( self::DONE ) ) {
			update_option( self::DONE, 1 );
		}

		$has_items = (int) wp_count_posts( 'niroroadmap_item' )->publish > count( Sample_Data::item_ids() );
		$samples   = Sample_Data::item_ids();
		$sample    = isset( $_GET['niroroadmap-sample'] ) ? sanitize_key( wp_unslash( $_GET['niroroadmap-sample'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		?>
		<div class="wrap nr-start">
			<h1><?php esc_html_e( 'Welcome to NiroRoadmap', 'niroroadmap' ); ?></h1>
			<p class="nr-start-lead"><?php esc_html_e( 'Share what you are building, and let your users vote on what comes next. Follow these steps to get your roadmap online. It takes about five minutes.', 'niroroadmap' ); ?></p>

			<?php if ( 'add' === $sample ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Example content added. Look at your roadmap page, then remove the examples from the same box when you are ready.', 'niroroadmap' ); ?></p></div>
			<?php elseif ( 'remove' === $sample ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Example content removed.', 'niroroadmap' ); ?></p></div>
			<?php endif; ?>

			<div class="nr-start-card nr-start-try">
				<div>
					<h2><?php esc_html_e( 'Want to see it working first?', 'niroroadmap' ); ?></h2>
					<p><?php esc_html_e( 'Fill your board with seven example items, two products, a few tags and some votes. We also check that your statuses and Roadmap page are in place. The examples don\'t count towards the steps below, and one click removes them. Your statuses and Roadmap page stay.', 'niroroadmap' ); ?></p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="niroroadmap_sample_items">
					<?php wp_nonce_field( 'niroroadmap_sample_items' ); ?>
					<?php if ( $samples ) : ?>
						<input type="hidden" name="do" value="remove">
						<?php submit_button( __( 'Remove example content', 'niroroadmap' ), 'secondary', 'submit', false ); ?>
					<?php else : ?>
						<input type="hidden" name="do" value="add">
						<?php submit_button( $has_items ? __( 'Add example content anyway', 'niroroadmap' ) : __( 'Add example content', 'niroroadmap' ), 'secondary', 'submit', false ); ?>
					<?php endif; ?>
				</form>
			</div>

			<div class="nr-start-card">
				<h2><?php esc_html_e( 'Set up your roadmap', 'niroroadmap' ); ?></h2>
				<p class="nr-start-progress">
					<?php
					/* translators: 1: completed steps, 2: total steps */
					echo esc_html( sprintf( __( '%1$d of %2$d steps done', 'niroroadmap' ), $completed, count( $steps ) ) );
					?>
				</p>
				<ol class="nr-start-steps">
					<?php foreach ( $steps as $step ) : ?>
						<li class="<?php echo $step['done'] ? 'is-done' : ''; ?>">
							<span class="nr-start-check" aria-hidden="true"><?php echo $step['done'] ? '&#10003;' : ''; ?></span>
							<div>
								<h3>
									<?php echo esc_html( $step['title'] ); ?>
									<?php if ( $step['done'] ) : ?>
										<span class="screen-reader-text"><?php esc_html_e( '(done)', 'niroroadmap' ); ?></span>
									<?php endif; ?>
								</h3>
								<p><?php echo esc_html( $step['text'] ); ?></p>
								<a class="button<?php echo $step['done'] ? '' : ' button-primary'; ?>" href="<?php echo esc_url( $step['url'] ); ?>"<?php echo ! empty( $step['blank'] ) ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( $step['label'] ); ?></a>
							</div>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>

			<div class="nr-start-card">
				<h2><?php esc_html_e( 'Show the roadmap on any page', 'niroroadmap' ); ?></h2>
				<p><?php esc_html_e( 'Add the Roadmap block in the editor, or paste a shortcode into a Shortcode block:', 'niroroadmap' ); ?></p>
				<p><code>[niroroadmap]</code></p>
				<p><?php esc_html_e( 'Options go inside the brackets. For example, one board per product with a search toolbar, opened as a timeline:', 'niroroadmap' ); ?></p>
				<p><code>[niroroadmap product="12" toolbar="yes" view="timeline" switcher="yes"]</code></p>
				<p class="description"><?php esc_html_e( 'Find a product\'s ID under NiroRoadmap → Products.', 'niroroadmap' ); ?></p>
			</div>

			<div class="nr-start-card">
				<h2><?php esc_html_e( 'Need help?', 'niroroadmap' ); ?></h2>
				<p>
					<a href="<?php echo esc_url( self::docs_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Visit our website', 'niroroadmap' ); ?></a>
					&middot;
					<a href="<?php echo esc_url( self::support_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ask in the support forum', 'niroroadmap' ); ?></a>
				</p>
			</div>
		</div>
		<?php
	}
}
