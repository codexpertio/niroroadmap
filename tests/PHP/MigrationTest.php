<?php
use NiroRoadmap\Bootstrap\Migrator;
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
		foreach ( array( Migrator::OPTION, Migrator::CURSOR, 'widget_text' ) as $option ) {
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
		wp_clear_scheduled_hook( Migrator::EVENT );
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

	public function test_a_product_tag_link_follows_the_roadmap_tag_and_leaves_shop_only_tags_alone() {
		$this->shop_tag_taxonomy();
		$tag              = $this->term( 'niroroadmap_tag' );
		$shop             = wp_insert_term( $tag->name, 'product_tag', array( 'slug' => $tag->slug ) );
		$this->term_ids[] = array( $shop['term_id'], 'product_tag' );
		$only_shop        = wp_insert_term( 'Shop only ' . wp_generate_password( 6, false, false ), 'product_tag' );
		$this->term_ids[] = array( $only_shop['term_id'], 'product_tag' );
		$shop_slug        = get_term( $only_shop['term_id'], 'product_tag' )->slug;

		$out = Legacy_Urls::replace( "/product-tag/{$tag->slug}/ /product-tag/{$shop_slug}/" );

		$this->assertStringContainsString( get_term_link( $tag ), $out, 'On 1.2.0 this address showed the roadmap tag.' );
		$this->assertStringContainsString( "/product-tag/{$shop_slug}/", $out );
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

	public function test_the_migration_runs_once_and_hands_posts_to_the_background() {
		update_option( 'widget_text', array( 2 => array( 'title' => 'T', 'text' => '<a href="/product-cat/planned/">P</a>' ), '_multiwidget' => 1 ) );
		delete_option( Migrator::OPTION );
		wp_clear_scheduled_hook( Migrator::EVENT );

		Migrator::maybe_run();

		$this->assertSame( Migrator::VERSION, get_option( Migrator::OPTION ) );
		$this->assertSame( 0, (int) get_option( Migrator::CURSOR ) );
		$this->assertNotFalse( wp_next_scheduled( Migrator::EVENT ) );
		$this->assertStringContainsString( '/roadmap-status/planned', get_option( 'widget_text' )[2]['text'], 'Widgets are done on the first load.' );

		wp_clear_scheduled_hook( Migrator::EVENT );
		Migrator::maybe_run();

		$this->assertNotFalse( wp_next_scheduled( Migrator::EVENT ), 'A lost batch event is scheduled again while posts remain.' );
	}

	public function test_the_background_batches_update_every_post_then_announce_once() {
		$product = $this->term( 'niroroadmap_product' );
		$ids     = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$ids[] = $this->post( array( 'post_content' => '<p><a href="/product-brand/' . $product->slug . '/">Web</a></p>' ) );
		}
		$fired          = 0;
		$this->listener = static function ( $bases ) use ( &$fired ) {
			++$fired;
		};
		add_action( 'niroroadmap_archive_urls_changed', $this->listener );
		update_option( Migrator::CURSOR, $ids[0] - 1 );

		for ( $i = 0; $i < 50 && false !== get_option( Migrator::CURSOR ); $i++ ) {
			Migrator::run_batch();
		}

		foreach ( $ids as $id ) {
			$this->assertStringContainsString( get_term_link( $product ), get_post_field( 'post_content', $id, 'raw' ) );
			$this->assertNotEmpty( wp_get_post_revisions( $id ), 'Each changed post keeps a revision.' );
		}
		$this->assertFalse( get_option( Migrator::CURSOR ) );
		$this->assertSame( 1, $fired );
		$this->assertSame(
			array( 'product-cat' => 'roadmap-status', 'product-brand' => 'roadmap-product', 'product-tag' => 'roadmap-tag' ),
			Legacy_Urls::bases()
		);

		Migrator::run_batch();
		$this->assertSame( 1, $fired, 'Nothing runs once the cursor is gone.' );
	}

	public function test_a_fresh_install_skips_the_migration_and_a_reactivated_site_does_not() {
		$activated = get_option( 'niroroadmap_activated' );
		$db        = get_option( 'niroroadmap_db_version' );

		delete_option( 'niroroadmap_activated' );
		delete_option( 'niroroadmap_db_version' );
		delete_option( Migrator::OPTION );
		\NiroRoadmap\Bootstrap\Installer::install();
		$fresh = get_option( Migrator::OPTION );

		update_option( 'niroroadmap_activated', true );
		delete_option( 'niroroadmap_db_version' );
		delete_option( Migrator::OPTION );
		\NiroRoadmap\Bootstrap\Installer::install();
		$reactivated = get_option( Migrator::OPTION );

		update_option( 'niroroadmap_activated', $activated );
		update_option( 'niroroadmap_db_version', $db );
		delete_transient( 'niroroadmap_activation_redirect' );

		$this->assertSame( Migrator::VERSION, $fresh );
		$this->assertFalse( $reactivated, 'Deactivation deletes the db version, so a reactivated site still migrates.' );
	}
}
