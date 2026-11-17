<?php
use NiroRoadmap\Bootstrap\Activator;
use NiroRoadmap\Controller\Front\Legacy_Urls;
use PHPUnit\Framework\TestCase;

/**
 * Archive URLs: the roadmap's own slugs, no clash with shop plugins, and the old URLs redirected.
 */
class RewriteTest extends TestCase {

	private $term_ids = array();

	private $counter = null;

	protected function tearDown(): void {
		foreach ( $this->term_ids as $term ) {
			wp_delete_term( $term[0], $term[1] );
		}
		if ( $this->counter ) {
			remove_action( 'generate_rewrite_rules', $this->counter );
		}
	}

	private function slugs() {
		return array(
			'niroroadmap_status'  => get_taxonomy( 'niroroadmap_status' )->rewrite['slug'],
			'niroroadmap_product' => get_taxonomy( 'niroroadmap_product' )->rewrite['slug'],
			'niroroadmap_tag'     => get_taxonomy( 'niroroadmap_tag' )->rewrite['slug'],
		);
	}

	public function test_the_taxonomies_use_their_own_slugs() {
		$this->assertSame(
			array(
				'niroroadmap_status'  => 'roadmap-status',
				'niroroadmap_product' => 'roadmap-product',
				'niroroadmap_tag'     => 'roadmap-tag',
			),
			$this->slugs()
		);
	}

	public function test_no_other_post_type_or_taxonomy_uses_a_roadmap_slug() {
		$ours   = $this->slugs();
		$others = array();

		foreach ( array_merge( get_taxonomies( array(), 'objects' ), get_post_types( array(), 'objects' ) ) as $name => $object ) {
			if ( ! isset( $ours[ $name ] ) && ! empty( $object->rewrite['slug'] ) ) {
				$others[ $name ] = $object->rewrite['slug'];
			}
		}

		$this->assertSame( array(), array_intersect( $others, $ours ) );
		$this->assertNotContains( 'product-tag', $ours, 'WooCommerce\'s default product tag base.' );
	}

	public function test_the_rewrite_rules_send_the_new_slugs_to_the_roadmap_and_leave_product_tag_alone() {
		flush_rewrite_rules( false );
		$rules = get_option( 'rewrite_rules' );

		$this->assertSame( 'index.php?niroroadmap_status=$matches[1]', $rules['roadmap-status/([^/]+)/?$'] );
		$this->assertSame( 'index.php?niroroadmap_product=$matches[1]', $rules['roadmap-product/([^/]+)/?$'] );
		$this->assertSame( 'index.php?niroroadmap_tag=$matches[1]', $rules['roadmap-tag/([^/]+)/?$'] );

		foreach ( $rules as $pattern => $query ) {
			if ( 0 === strpos( $pattern, 'product-' ) ) {
				$this->assertStringNotContainsString( 'niroroadmap_', $query, "{$pattern} still points at the roadmap." );
			}
		}
	}

	public function test_old_archive_urls_map_to_the_current_ones() {
		$tag              = wp_insert_term( 'Legacy ' . wp_generate_password( 5, false ), 'niroroadmap_tag' );
		$this->term_ids[] = array( $tag['term_id'], 'niroroadmap_tag' );
		$planned          = get_term_by( 'slug', 'planned', 'niroroadmap_status' );
		$tag_slug         = get_term( $tag['term_id'] )->slug;

		$this->assertSame( get_term_link( $planned ), Legacy_Urls::new_url( 'product-cat/planned' ) );
		$this->assertSame( get_term_link( $planned ), Legacy_Urls::new_url( 'product-cat/planned/' ) );
		$this->assertSame( get_term_link( (int) $tag['term_id'], 'niroroadmap_tag' ), Legacy_Urls::new_url( "product-tag/{$tag_slug}" ) );
		$this->assertStringContainsString( '/roadmap-status/planned', Legacy_Urls::new_url( 'product-cat/planned' ) );
	}

	public function test_anything_else_is_not_redirected() {
		$this->assertSame( '', Legacy_Urls::new_url( 'product-cat/no-such-status-' . wp_generate_password( 5, false ) ) );
		$this->assertSame( '', Legacy_Urls::new_url( 'product-category/planned' ), 'WooCommerce\'s category base is not ours.' );
		$this->assertSame( '', Legacy_Urls::new_url( 'product-cat/planned/comments' ) );
		$this->assertSame( '', Legacy_Urls::new_url( 'roadmap/' ) );
	}

	public function test_the_rules_are_flushed_once_per_rewrite_version() {
		$flushes       = 0;
		$this->counter = static function () use ( &$flushes ) {
			++$flushes;
		};
		add_action( 'generate_rewrite_rules', $this->counter );

		delete_option( 'niroroadmap_rewrite_version' );
		Activator::maybe_flush_rewrites();
		Activator::maybe_flush_rewrites();

		$this->assertSame( 1, $flushes, 'Flushed on the first load only.' );
		$this->assertSame( Activator::REWRITE_VERSION, get_option( 'niroroadmap_rewrite_version' ) );
	}
}
