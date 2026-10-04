<?php
use NiroRoadmap\Helper\Settings;
use NiroRoadmap\Model\Roadmap;
use NiroRoadmap\Model\Toolbar;
use PHPUnit\Framework\TestCase;

/**
 * The search / sort / filter toolbar: what a board is offered, and what it renders.
 */
class ToolbarTest extends TestCase {

	private $item_ids = array();

	private $term_ids = array();

	private $overrides = array();

	protected function setUp(): void {
		unset( $GLOBALS['wp_actions']['niroroadmap_suggest_rendered'], $GLOBALS['wp_actions']['niroroadmap_modal_rendered'] );
		wp_dequeue_script( 'niroroadmap-toolbar' );
		wp_dequeue_style( 'niroroadmap-toolbar' );

		// The site's saved settings must not decide these tests.
		foreach ( array(
			'toolbar_enabled'        => false,
			'toolbar_sort'           => 'manual',
			'toolbar_filter_search'  => true,
			'toolbar_filter_tag'     => true,
			'toolbar_filter_product' => true,
			'show_tags'              => true,
			'show_vote_counts'       => true,
			'default_product'        => 0,
			'comments_enabled'       => false,
			'submissions_enabled'    => false,
		) as $key => $value ) {
			$this->set( $key, $value );
		}
	}

	protected function tearDown(): void {
		foreach ( $this->item_ids as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $this->term_ids as $term ) {
			wp_delete_term( $term[0], $term[1] );
		}
		foreach ( $this->overrides as $hook => $callback ) {
			remove_filter( $hook, $callback );
		}
	}

	private function set( $key, $value ) {
		$hook = "niroroadmap_setting_{$key}";

		if ( isset( $this->overrides[ $hook ] ) ) {
			remove_filter( $hook, $this->overrides[ $hook ] );
		}

		$this->overrides[ $hook ] = static function () use ( $value ) {
			return $value;
		};
		add_filter( $hook, $this->overrides[ $hook ] );
	}

	private function term( $taxonomy, $name ) {
		$term = wp_insert_term( $name, $taxonomy );
		$this->term_ids[] = array( $term['term_id'], $taxonomy );

		return $term['term_id'];
	}

	/**
	 * A published item in the first status, with the given tags and products.
	 */
	private function item( $title, $tags = array(), $products = array() ) {
		$id = wp_insert_post( array( 'post_type' => 'niroroadmap_item', 'post_status' => 'publish', 'post_title' => $title ) );
		$this->item_ids[] = $id;

		wp_set_object_terms( $id, array( (int) get_term_by( 'slug', 'under-review', 'niroroadmap_status' )->term_id ), 'niroroadmap_status' );
		$tags && wp_set_object_terms( $id, $tags, 'niroroadmap_tag' );
		$products && wp_set_object_terms( $id, $products, 'niroroadmap_product' );

		return $id;
	}

	private function board( $args = array(), $product = null ) {
		unset( $GLOBALS['wp_actions']['niroroadmap_modal_rendered'] );

		return Roadmap::get_roadmap( $product, $args + array( 'toolbar' => 'yes' ) );
	}

	// --- Config ----------------------------------------------------------------------------------

	public function test_the_toolbar_ships_off_and_a_board_can_override_it() {
		$this->assertFalse( Settings::default_for( 'toolbar_enabled' ) );
		$this->assertSame( 'manual', Settings::default_for( 'toolbar_sort' ) );
		$this->assertTrue( Settings::default_for( 'toolbar_filter_search' ) );

		$this->assertFalse( Toolbar::config()['enabled'] );
		$this->assertTrue( Toolbar::config( array( 'toolbar' => 'yes' ) )['enabled'] );
		$this->assertTrue( Toolbar::config( array( 'toolbar' => 'YES' ) )['enabled'] );
		$this->assertFalse( Toolbar::config( array( 'toolbar' => 'maybe' ) )['enabled'], 'Anything else follows the site setting.' );

		$this->set( 'toolbar_enabled', true );
		$this->assertTrue( Toolbar::config()['enabled'] );
		$this->assertFalse( Toolbar::config( array( 'toolbar' => 'no' ) )['enabled'] );
	}

	public function test_a_sort_the_board_cant_offer_falls_back() {
		$this->assertSame( 'manual', Toolbar::config()['sort'] );
		$this->assertSame( 'newest', Toolbar::config( array( 'sort' => 'newest' ) )['sort'] );
		$this->assertSame( 'manual', Toolbar::config( array( 'sort' => 'bogus' ) )['sort'] );

		// Votes can't be a sort when the numbers are hidden: ordering would reveal them.
		$this->set( 'show_vote_counts', false );
		$this->assertArrayNotHasKey( 'votes', Toolbar::config()['sorts'] );
		$this->assertSame( 'manual', Toolbar::config( array( 'sort' => 'votes' ) )['sort'] );

		// Likewise "Most commented" needs comments.
		$this->assertArrayNotHasKey( 'commented', Toolbar::config()['sorts'] );
		$this->set( 'comments_enabled', true );
		$this->assertArrayHasKey( 'commented', Toolbar::config()['sorts'] );

		// The site's initial sort is also checked against what's offered.
		$this->set( 'toolbar_sort', 'votes' );
		$this->assertSame( 'manual', Toolbar::config()['sort'] );
	}

	public function test_filters_follow_the_setting_unless_the_board_names_them() {
		$this->assertSame( array( 'search' => true, 'tag' => true, 'product' => true ), Toolbar::config()['filters'] );

		$this->set( 'toolbar_filter_product', false );
		$this->assertFalse( Toolbar::config()['filters']['product'] );

		$only = Toolbar::config( array( 'filters' => 'search, TAG' ) )['filters'];
		$this->assertSame( array( 'search' => true, 'tag' => true, 'product' => false ), $only, 'The board decides, case and spaces aside.' );

		$this->assertSame( array( 'search' => false, 'tag' => false, 'product' => false ), Toolbar::config( array( 'filters' => 'none' ) )['filters'] );
		$this->assertSame( array( 'search' => false, 'tag' => false, 'product' => false ), Toolbar::config( array( 'filters' => 'bogus,<script>' ) )['filters'], 'Unknown names are ignored.' );

		$this->set( 'show_tags', false );
		$this->assertFalse( Toolbar::config( array( 'filters' => 'search,tag' ) )['filters']['tag'], 'Hidden tags can not be filtered by.' );
	}

	// --- Rendering -------------------------------------------------------------------------------

	public function test_a_board_without_a_toolbar_renders_none_and_loads_nothing_extra() {
		$this->item( 'No toolbar item' );

		$html = Roadmap::get_roadmap();

		$this->assertStringNotContainsString( 'data-nr-toolbar', $html );
		$this->assertStringNotContainsString( 'class="nr-toolbar"', $html );
		$this->assertStringNotContainsString( 'data-date=', $html );
		$this->assertStringNotContainsString( 'data-nomatch', $html );
		$this->assertFalse( wp_script_is( 'niroroadmap-toolbar', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'niroroadmap-toolbar', 'enqueued' ) );
	}

	public function test_the_extra_lookups_only_happen_when_there_is_a_toolbar() {
		$this->item( 'Lookup item' );
		$lookups = 0;
		$count   = static function ( $terms, $post_id, $taxonomy ) use ( &$lookups ) {
			if ( 'niroroadmap_product' === $taxonomy ) {
				++$lookups;
			}

			return $terms;
		};
		add_filter( 'get_the_terms', $count, 10, 3 );

		Roadmap::get_roadmap( null, array( 'toolbar' => 'no' ) );
		$this->assertSame( 0, $lookups, 'No product lookups for a board without a toolbar.' );

		unset( $GLOBALS['wp_actions']['niroroadmap_modal_rendered'] );
		Roadmap::get_roadmap( null, array( 'toolbar' => 'yes' ) );
		$this->assertGreaterThan( 0, $lookups, 'A toolbar needs them.' );

		remove_filter( 'get_the_terms', $count, 10 );
	}

	public function test_a_board_with_a_toolbar_renders_it_and_loads_its_assets() {
		$tag = $this->term( 'niroroadmap_tag', 'Payments ' . wp_generate_password( 4, false ) );
		$id  = $this->item( 'Toolbar item', array( $tag ) );

		$html = $this->board( array( 'sort' => 'newest' ) );

		$this->assertStringContainsString( 'data-nr-toolbar', $html );
		$this->assertStringContainsString( 'data-nr-default-sort="newest"', $html );
		$this->assertStringContainsString( 'data-nr-search', $html );
		$this->assertMatchesRegularExpression( '/<option value="newest"\s+selected=\'selected\'/', $html );
		$this->assertStringContainsString( 'data-nomatch=', $html );
		$this->assertTrue( wp_script_is( 'niroroadmap-toolbar', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'niroroadmap-toolbar', 'enqueued' ) );

		// The card carries what the script filters and sorts by.
		$this->assertMatchesRegularExpression( '/id="nr-task-' . $id . '"[^>]*data-date="\d+"[^>]*data-product-ids=""[^>]*data-tag-slugs="[a-z0-9-]+"/', $html );
	}

	public function test_the_suggest_button_joins_the_toolbar_row_instead_of_standing_alone() {
		$this->item( 'Suggest placement' );

		// Both on: one button, inside the toolbar's bar, ahead of the columns.
		$both = $this->board( array( 'submissions' => 'yes' ) );
		$this->assertSame( 1, substr_count( $both, 'class="nr-suggest-btn"' ), 'One Suggest button, not two.' );
		$this->assertStringNotContainsString( 'nr-board-toolbar', $both, 'No separate row for it.' );
		$bar   = strpos( $both, 'class="nr-toolbar-bar"' );
		$btn   = strpos( $both, 'class="nr-suggest-btn"' );
		$board = strpos( $both, 'class="nr-kanban-columns"' );
		$this->assertTrue( false !== $bar && $bar < $btn && $btn < $board, 'The button is inside the toolbar bar.' );

		// Toolbar off, suggest on: the button keeps its own row, as before.
		unset( $GLOBALS['wp_actions']['niroroadmap_suggest_rendered'], $GLOBALS['wp_actions']['niroroadmap_modal_rendered'] );
		$alone = Roadmap::get_roadmap( null, array( 'toolbar' => 'no', 'submissions' => 'yes' ) );
		$this->assertStringContainsString( 'nr-board-toolbar', $alone );
		$this->assertSame( 1, substr_count( $alone, 'class="nr-suggest-btn"' ) );

		// Toolbar on, suggest off: no button anywhere.
		unset( $GLOBALS['wp_actions']['niroroadmap_suggest_rendered'] );
		$this->assertStringNotContainsString( 'nr-suggest-btn', $this->board( array( 'submissions' => 'no' ) ) );
	}

	public function test_tags_are_in_a_dropdown_whose_button_is_wired_to_its_panel() {
		$tag = $this->term( 'niroroadmap_tag', 'Dropdown ' . wp_generate_password( 4, false ) );
		$this->item( 'Dropdown item', array( $tag ) );

		$html = $this->board();

		$this->assertMatchesRegularExpression( '/<button[^>]*data-nr-tags-toggle[^>]*aria-expanded="false"[^>]*aria-controls="(nr-board-\d+-tags)"/', $html );
		preg_match( '/aria-controls="(nr-board-\d+-tags)"/', $html, $m );
		$this->assertStringContainsString( 'id="' . $m[1] . '"', $html, 'The panel the button controls exists.' );
		$this->assertMatchesRegularExpression( '/<fieldset class="nr-tags-panel"[^>]*hidden>\s*<legend class="nr-sr-only">/', $html, 'Closed by default, and labelled.' );
	}

	public function test_only_the_sorts_the_board_can_offer_are_in_the_menu() {
		$this->item( 'Menu item' );
		$this->set( 'show_vote_counts', false );

		$html = $this->board();

		$this->assertStringNotContainsString( 'value="votes"', $html );
		$this->assertStringNotContainsString( 'value="commented"', $html );
		$this->assertStringContainsString( 'value="newest"', $html );
		$this->assertStringContainsString( 'value="oldest"', $html );
	}

	public function test_tag_chips_list_the_tags_in_use_and_escape_them() {
		$nasty = '<img src=x onerror=alert(1)> "q" ' . wp_generate_password( 4, false );
		$tag   = $this->term( 'niroroadmap_tag', $nasty );
		$this->item( 'Chip item', array( $tag ) );

		$html = $this->board();

		$this->assertStringContainsString( 'data-nr-tag=', $html );
		$this->assertStringNotContainsString( '<img src=x', $html );
		$this->assertStringNotContainsString( 'onerror=alert(1)>', $html );
	}

	public function test_no_tag_chips_or_tag_data_when_tags_are_hidden_on_the_board() {
		$tag = $this->term( 'niroroadmap_tag', 'Hidden ' . wp_generate_password( 4, false ) );
		$this->item( 'Hidden tag item', array( $tag ) );
		$this->set( 'show_tags', false );

		$html = $this->board();

		$this->assertStringNotContainsString( 'data-nr-tag=', $html );
		$this->assertStringNotContainsString( 'data-tag-slugs=', $html );
	}

	public function test_the_product_filter_needs_several_products_and_an_unlocked_board() {
		$a = $this->term( 'niroroadmap_product', 'Alpha ' . wp_generate_password( 4, false ) );
		$b = $this->term( 'niroroadmap_product', 'Beta ' . wp_generate_password( 4, false ) );
		$this->item( 'Alpha item', array(), array( $a ) );
		$this->item( 'Beta item', array(), array( $b ) );

		$this->assertStringContainsString( 'data-nr-product', $this->board(), 'Several products on an unfiltered board.' );
		$this->assertStringNotContainsString( 'data-nr-product', $this->board( array(), $a ), 'A board limited to one product has nothing to filter by.' );
		$this->assertStringNotContainsString( 'data-nr-product', $this->board( array( 'filters' => 'search,tag' ) ), 'The board left it out.' );

		$this->set( 'toolbar_filter_product', false );
		$this->assertStringNotContainsString( 'data-nr-product', $this->board(), 'The site setting left it out.' );
	}

	public function test_one_product_is_not_worth_a_filter() {
		$only = $this->term( 'niroroadmap_product', 'Solo ' . wp_generate_password( 4, false ) );
		// Every item on the board belongs to the same product, so there's nothing to choose between.
		$html = Roadmap::get_roadmap( $only, array( 'toolbar' => 'yes' ) );

		$this->assertStringNotContainsString( 'data-nr-product', $html );
	}

	public function test_the_shortcode_and_the_block_pass_their_options_through() {
		$this->item( 'Pass through' );

		$short = ( new \NiroRoadmap\Controller\Public\Shortcode() )->callback_roadmap( array( 'toolbar' => 'yes', 'sort' => 'oldest', 'filters' => 'search' ) );
		$this->assertStringContainsString( 'data-nr-default-sort="oldest"', $short );
		$this->assertStringContainsString( 'data-nr-search', $short );
		$this->assertStringNotContainsString( 'data-nr-tag=', $short );

		$off = ( new \NiroRoadmap\Controller\Public\Shortcode() )->callback_roadmap( array( 'toolbar' => 'no' ) );
		$this->assertStringNotContainsString( 'data-nr-toolbar', $off );

		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'niroroadmap/roadmap' ) ) {
			$this->markTestIncomplete( 'The block is not built here (npm run build:blocks), so only the shortcode was checked.' );
		}

		unset( $GLOBALS['wp_actions']['niroroadmap_modal_rendered'] );
		$block = render_block( array( 'blockName' => 'niroroadmap/roadmap', 'attrs' => array( 'toolbar' => 'yes', 'sort' => 'newest', 'filters' => 'none' ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
		$this->assertStringContainsString( 'data-nr-default-sort="newest"', $block );
		$this->assertStringNotContainsString( 'data-nr-search', $block );
		$this->assertStringContainsString( 'data-nr-sort', $block, 'Sorting stays; "none" only removes filters.' );
	}

	public function test_the_toolbar_settings_are_validated() {
		$ok = Settings::sanitize( array( '_tab' => 'toolbar', 'toolbar_sort' => 'newest', 'toolbar_enabled' => '1' ) );
		$this->assertSame( 'newest', $ok['toolbar_sort'] );
		$this->assertTrue( $ok['toolbar_enabled'] );
		$this->assertFalse( $ok['toolbar_filter_tag'], 'An unticked box in a submitted tab is off.' );

		$bad = Settings::sanitize( array( '_tab' => 'toolbar', 'toolbar_sort' => 'bogus' ) );
		$this->assertNotSame( 'bogus', $bad['toolbar_sort'] ?? null );
	}
}
