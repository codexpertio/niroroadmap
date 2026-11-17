<?php
use NiroRoadmap\Bootstrap\Migrator;
use NiroRoadmap\Controller\Admin\Link_Migration;
use NiroRoadmap\Controller\Front\Legacy_Urls;
use PHPUnit\Framework\TestCase;

/**
 * Links stored with the old archive URLs move to the new ones after an update.
 */
class MigrationTest extends TestCase {

	private $post_ids = array();

	private $term_ids = array();

	private $options = array();

	private $listener = null;

	protected function setUp(): void {
		foreach ( array( Migrator::OPTION, Migrator::REPORT, 'widget_text' ) as $option ) {
			$this->options[ $option ] = get_option( $option );
		}
	}

	protected function tearDown(): void {
		foreach ( $this->post_ids as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $this->term_ids as $term ) {
			wp_delete_term( $term[0], $term[1] );
		}
		foreach ( $this->options as $option => $value ) {
			false === $value ? delete_option( $option ) : update_option( $option, $value );
		}
		if ( $this->listener ) {
			remove_action( 'niroroadmap_archive_urls_changed', $this->listener );
		}
		delete_transient( 'niroroadmap_migrating' );
	}

	private function term( $taxonomy ) {
		$term             = wp_insert_term( 'Mig ' . wp_generate_password( 6, false, false ), $taxonomy );
		$this->term_ids[] = array( $term['term_id'], $taxonomy );

		return get_term( $term['term_id'], $taxonomy );
	}

	private function post( $args ) {
		$id               = wp_insert_post( array_merge( array( 'post_status' => 'publish', 'post_title' => 'Mig test' ), $args ) );
		$this->post_ids[] = $id;

		return $id;
	}

	private function shop_tag_taxonomy() {
		if ( ! taxonomy_exists( 'product_tag' ) ) {
			register_taxonomy( 'product_tag', 'post', array( 'rewrite' => array( 'slug' => 'product-tag' ) ) );
		}
	}

	public function test_old_feed_and_paged_urls_map_to_the_new_ones() {
		$planned = get_term_by( 'slug', 'planned', 'niroroadmap_status' );
		$link    = get_term_link( $planned );

		$this->assertSame( trailingslashit( $link ) . user_trailingslashit( 'page/2', 'paged' ), Legacy_Urls::new_url( 'product-cat/planned/page/2' ) );
		$this->assertSame( get_term_feed_link( $planned->term_id, 'niroroadmap_status' ), Legacy_Urls::new_url( 'product-cat/planned/feed' ) );
		$this->assertSame( get_term_feed_link( $planned->term_id, 'niroroadmap_status', 'atom' ), Legacy_Urls::new_url( 'product-cat/planned/feed/atom/' ) );
	}

	public function test_replace_rewrites_this_sites_old_links_only() {
		$product = $this->term( 'niroroadmap_product' );
		$home    = untrailingslashit( home_url() );
		$html    = '<a href="' . $home . '/product-brand/' . $product->slug . '/">A</a> <a href="/product-cat/planned/feed/">B</a> <a href="https://example.org/product-cat/planned/">C</a> <a href="' . $home . '/shop/product-cat/planned/">D</a>';

		$out = Legacy_Urls::replace( $html );

		$this->assertStringContainsString( 'href="' . get_term_link( $product ) . '"', $out );
		$this->assertStringContainsString( 'href="' . get_term_feed_link( get_term_by( 'slug', 'planned', 'niroroadmap_status' )->term_id, 'niroroadmap_status' ) . '"', $out );
		$this->assertStringContainsString( 'https://example.org/product-cat/planned/', $out, 'Another site\'s link is not touched.' );
		$this->assertStringContainsString( $home . '/shop/product-cat/planned/', $out, 'A longer path is not ours.' );
		$this->assertSame( 'no links here', Legacy_Urls::replace( 'no links here' ) );
	}

	public function test_a_product_tag_link_a_shop_term_may_own_is_left_alone() {
		$this->shop_tag_taxonomy();
		$tag              = $this->term( 'niroroadmap_tag' );
		$shop             = wp_insert_term( $tag->name, 'product_tag', array( 'slug' => $tag->slug ) );
		$this->term_ids[] = array( $shop['term_id'], 'product_tag' );
		$other            = $this->term( 'niroroadmap_tag' );
		$skipped          = 0;

		$out = Legacy_Urls::replace( "/product-tag/{$tag->slug}/ /product-tag/{$other->slug}/", $skipped );

		$this->assertStringContainsString( "/product-tag/{$tag->slug}/", $out );
		$this->assertStringContainsString( get_term_link( $other ), $out );
		$this->assertSame( 1, $skipped );
	}

	public function test_menu_custom_links_are_migrated() {
		$menu    = wp_create_nav_menu( 'Mig menu ' . wp_generate_password( 6, false, false ) );
		$item_id = wp_update_nav_menu_item(
			$menu,
			0,
			array(
				'menu-item-title'  => 'Planned',
				'menu-item-url'    => home_url( '/product-cat/planned/' ),
				'menu-item-status' => 'publish',
				'menu-item-type'   => 'custom',
			)
		);

		$changed = Migrator::migrate_menu_links();
		$url     = get_post_meta( $item_id, '_menu_item_url', true );
		wp_delete_nav_menu( $menu );

		$this->assertGreaterThanOrEqual( 1, $changed );
		$this->assertSame( get_term_link( get_term_by( 'slug', 'planned', 'niroroadmap_status' ) ), $url );
	}

	public function test_navigation_blocks_are_migrated() {
		$tag    = $this->term( 'niroroadmap_tag' );
		$markup = '<!-- wp:navigation-link {"label":"Tag","type":"niroroadmap_tag","id":' . $tag->term_id . ',"url":"' . home_url( '/product-tag/' . $tag->slug . '/' ) . '","kind":"taxonomy"} /-->'
			. '<!-- wp:navigation-link {"label":"Custom","url":"' . home_url( '/product-cat/planned/' ) . '","kind":"custom"} /-->'
			. '<!-- wp:navigation-link {"label":"Other","url":"https://example.org/product-cat/planned/","kind":"custom"} /-->';
		$id     = $this->post( array( 'post_type' => 'wp_navigation', 'post_content' => $markup ) );

		Migrator::migrate_navigation();
		$blocks = parse_blocks( get_post_field( 'post_content', $id, 'raw' ) );

		$this->assertSame( get_term_link( $tag ), $blocks[0]['attrs']['url'] );
		$this->assertSame( get_term_link( get_term_by( 'slug', 'planned', 'niroroadmap_status' ) ), $blocks[1]['attrs']['url'] );
		$this->assertSame( 'https://example.org/product-cat/planned/', $blocks[2]['attrs']['url'] );
	}

	public function test_a_taxonomy_navigation_link_follows_its_term_even_on_a_shop_tag_address() {
		$this->shop_tag_taxonomy();
		$tag              = $this->term( 'niroroadmap_tag' );
		$shop             = wp_insert_term( $tag->name, 'product_tag', array( 'slug' => $tag->slug ) );
		$this->term_ids[] = array( $shop['term_id'], 'product_tag' );
		$markup           = '<!-- wp:navigation-link {"label":"Tag","type":"niroroadmap_tag","id":' . $tag->term_id . ',"url":"/product-tag/' . $tag->slug . '/","kind":"taxonomy"} /-->';

		$blocks = parse_blocks( Migrator::migrate_navigation_blocks( $markup ) );

		$this->assertSame( get_term_link( $tag ), $blocks[0]['attrs']['url'] );
	}

	public function test_the_migration_runs_once_reports_and_announces() {
		$fired          = 0;
		$this->listener = static function ( $bases ) use ( &$fired ) {
			++$fired;
		};
		add_action( 'niroroadmap_archive_urls_changed', $this->listener );
		delete_option( Migrator::OPTION );

		Migrator::maybe_run();
		Migrator::maybe_run();

		$this->assertSame( 1, $fired );
		$this->assertSame( Migrator::VERSION, get_option( Migrator::OPTION ) );
		$this->assertSame( array( 'menus', 'navigation', 'skipped' ), array_keys( get_option( Migrator::REPORT ) ) );
		$this->assertSame(
			array( 'product-cat' => 'roadmap-status', 'product-brand' => 'roadmap-product', 'product-tag' => 'roadmap-tag' ),
			Legacy_Urls::bases()
		);
	}

	public function test_a_fresh_install_skips_the_migration() {
		$db = get_option( 'niroroadmap_db_version' );
		delete_option( 'niroroadmap_db_version' );
		delete_option( Migrator::OPTION );

		\NiroRoadmap\Bootstrap\Installer::install();
		$marked = get_option( Migrator::OPTION );
		update_option( 'niroroadmap_db_version', $db );
		delete_transient( 'niroroadmap_activation_redirect' );

		$this->assertSame( Migrator::VERSION, $marked );
	}

	public function test_posts_and_widgets_are_updated_and_keep_a_revision() {
		$product = $this->term( 'niroroadmap_product' );
		$id      = $this->post( array( 'post_content' => '<p><a href="/product-brand/' . $product->slug . '/">Web</a></p>' ) );
		update_option( 'widget_text', array( 2 => array( 'title' => 'T', 'text' => '<a href="/product-cat/planned/">P</a>' ), '_multiwidget' => 1 ) );

		$found = wp_list_pluck( Link_Migration::find_posts( $id - 1, 10 ), 'ID' );
		Migrator::update_content( $id, Legacy_Urls::replace( get_post_field( 'post_content', $id, 'raw' ) ) );
		$widgets = Link_Migration::update_widgets();

		$this->assertContains( (string) $id, array_map( 'strval', $found ) );
		$this->assertStringContainsString( get_term_link( $product ), get_post_field( 'post_content', $id, 'raw' ) );
		$this->assertNotEmpty( wp_get_post_revisions( $id ) );
		$this->assertSame( 1, $widgets );
		$this->assertStringContainsString( '/roadmap-status/planned', get_option( 'widget_text' )[2]['text'] );
	}
}
