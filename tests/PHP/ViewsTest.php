<?php
use NiroRoadmap\Helper\Settings;
use NiroRoadmap\Model\Fields;
use NiroRoadmap\Model\Roadmap;
use NiroRoadmap\Model\Views;
use PHPUnit\Framework\TestCase;

/**
 * The Board / List / Timeline views: what a board is offered, how the timeline groups items, and
 * what each view renders.
 */
class ViewsTest extends TestCase {

	private $item_ids = array();

	private $overrides = array();

	protected function setUp(): void {
		unset( $GLOBALS['wp_actions']['niroroadmap_suggest_rendered'], $GLOBALS['wp_actions']['niroroadmap_modal_rendered'] );
		wp_dequeue_script( 'niroroadmap-views' );
		wp_dequeue_style( 'niroroadmap-views' );

		// The site's saved settings must not decide these tests.
		foreach ( array(
			'view_default'        => 'board',
			'view_switcher'       => false,
			'view_mobile_list'    => false,
			'view_timeline_group' => 'quarter',
			'show_target'         => true,
			'show_tags'           => true,
			'show_vote_counts'    => true,
			'toolbar_enabled'     => false,
			'default_product'     => 0,
			'comments_enabled'    => false,
			'submissions_enabled' => false,
		) as $key => $value ) {
			$this->set( $key, $value );
		}
	}

	protected function tearDown(): void {
		foreach ( $this->item_ids as $id ) {
			wp_delete_post( $id, true );
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

	/**
	 * A published item in the given status, with an optional target.
	 */
	private function item( $title, $target = '', $status = 'under-review' ) {
		$id = wp_insert_post( array( 'post_type' => 'niroroadmap_item', 'post_status' => 'publish', 'post_title' => $title ) );
		$this->item_ids[] = $id;

		wp_set_object_terms( $id, array( (int) get_term_by( 'slug', $status, 'niroroadmap_status' )->term_id ), 'niroroadmap_status' );

		if ( '' !== $target ) {
			Fields::save( $id, Fields::sanitize( array( 'target' => $target ) )['values'] );
		}

		return $id;
	}

	private function board( $args = array() ) {
		unset( $GLOBALS['wp_actions']['niroroadmap_modal_rendered'] );

		return Roadmap::get_roadmap( null, $args );
	}

	private function dated( $target, $index = 0, $text = null ) {
		return array(
			'target'      => $text ?? $target,
			'target_sort' => $target,
			'index'       => $index,
		);
	}

	private function labels( $groups ) {
		return wp_list_pluck( $groups, 'label' );
	}

	// --- Config ----------------------------------------------------------------------------------

	public function test_boards_ship_as_they_were_and_can_override_the_site() {
		$this->assertSame( 'board', Settings::default_for( 'view_default' ) );
		$this->assertFalse( Settings::default_for( 'view_switcher' ) );
		$this->assertFalse( Settings::default_for( 'view_mobile_list' ) );
		$this->assertSame( 'quarter', Settings::default_for( 'view_timeline_group' ) );

		$config = Views::config();
		$this->assertSame( 'board', $config['default'] );
		$this->assertFalse( $config['switcher'] );
		$this->assertSame( array( 'board' ), $config['render'], 'Only the view that is shown is put in the page.' );

		$this->assertSame( 'list', Views::config( array( 'view' => 'list' ) )['default'] );
		$this->assertSame( 'list', Views::config( array( 'view' => 'LIST' ) )['default'] );
		$this->assertSame( array( 'list' ), Views::config( array( 'view' => 'list' ) )['render'] );
		$this->assertSame( 'board', Views::config( array( 'view' => 'bogus' ) )['default'], 'Anything else follows the site setting.' );

		$this->set( 'view_default', 'timeline' );
		$this->assertSame( 'timeline', Views::config()['default'] );
		$this->assertSame( 'list', Views::config( array( 'view' => 'list' ) )['default'] );
	}

	public function test_the_switcher_offers_every_view_the_board_can_show() {
		$config = Views::config( array( 'switcher' => 'yes' ) );

		$this->assertTrue( $config['switcher'] );
		$this->assertSame( array( 'board', 'list', 'timeline' ), $config['render'] );
		$this->assertSame( array( 'board', 'list', 'timeline' ), array_keys( $config['views'] ) );

		$this->set( 'view_switcher', true );
		$this->assertTrue( Views::config()['switcher'] );
		$this->assertFalse( Views::config( array( 'switcher' => 'no' ) )['switcher'] );
		$this->assertSame( array( 'board' ), Views::config( array( 'switcher' => 'no' ) )['render'] );
	}

	public function test_the_timeline_is_only_offered_when_targets_are_public() {
		$this->set( 'show_target', false );

		$config = Views::config( array( 'view' => 'timeline', 'switcher' => 'yes' ) );

		$this->assertSame( 'board', $config['default'], 'No way to reach a view that would show hidden targets.' );
		$this->assertSame( array( 'board', 'list' ), $config['render'] );
		$this->assertArrayNotHasKey( 'timeline', $config['views'] );

		$this->set( 'view_default', 'timeline' );
		$this->assertSame( 'board', Views::config()['default'] );
	}

	public function test_a_mobile_list_needs_the_list_and_a_different_default() {
		$this->set( 'view_mobile_list', true );

		$config = Views::config();
		$this->assertSame( 'list', $config['mobile'] );
		$this->assertSame( array( 'board', 'list' ), $config['render'], 'Both are in the page; a script picks by screen width.' );

		$this->assertSame( '', Views::config( array( 'view' => 'list' ) )['mobile'], 'Already the list.' );
	}

	public function test_the_timeline_grouping_follows_the_board_or_the_site() {
		$this->assertSame( 'quarter', Views::config()['group'] );
		$this->assertSame( 'month', Views::config( array( 'group' => 'month' ) )['group'] );
		$this->assertSame( 'quarter', Views::config( array( 'group' => 'bogus' ) )['group'] );

		$this->set( 'view_timeline_group', 'nownext' );
		$this->assertSame( 'nownext', Views::config()['group'] );
		$this->assertSame( 'month', Views::config( array( 'group' => 'month' ) )['group'] );
	}

	// --- Grouping --------------------------------------------------------------------------------

	public function test_quarters_run_in_time_order_across_a_year_boundary() {
		$groups = Views::timeline_groups(
			array(
				$this->dated( '2027-02-10', 0 ),
				$this->dated( '2026-12-31', 1 ),
				$this->dated( '2026-10-01', 2 ),
				$this->dated( '2027-01-01', 3 ),
				$this->dated( '2026-09-30', 4 ),
			),
			'quarter',
			'2026-10-04'
		);

		$this->assertSame( array( 'Q3 2026', 'Q4 2026', 'Q1 2027' ), $this->labels( $groups ) );
		$this->assertSame( array( 4 ), wp_list_pluck( $groups[0]['items'], 'index' ) );
		$this->assertSame( array( 2, 1 ), wp_list_pluck( $groups[1]['items'], 'index' ), 'Inside a group, earliest date first.' );
		$this->assertSame( array( 3, 0 ), wp_list_pluck( $groups[2]['items'], 'index' ) );
	}

	public function test_items_without_a_date_go_last_and_empty_groups_are_left_out() {
		$groups = Views::timeline_groups(
			array(
				array( 'target' => '', 'target_sort' => '', 'index' => 0 ),
				$this->dated( '2026-11-05', 1 ),
				array( 'target' => 'soon', 'target_sort' => 'not a date', 'index' => 2 ),
			),
			'quarter',
			'2026-10-04'
		);

		$this->assertSame( array( 'Q4 2026', 'Later / Unscheduled' ), $this->labels( $groups ) );
		$this->assertSame( array( 0, 2 ), wp_list_pluck( $groups[1]['items'], 'index' ), 'Unscheduled items keep the board\'s order.' );
		$this->assertSame( array(), Views::timeline_groups( array(), 'quarter', '2026-10-04' ) );
	}

	public function test_a_quarter_typed_as_a_target_stays_a_quarter_when_grouping_by_month() {
		$groups = Views::timeline_groups(
			array(
				$this->dated( '2026-11-20', 0 ),
				$this->dated( '2026-10-01', 1, 'Q4 2026' ),
				$this->dated( '2026-10-01', 2 ),
			),
			'month',
			'2026-10-04'
		);

		$this->assertSame( array( 'Q4 2026', 'October 2026', 'November 2026' ), $this->labels( $groups ) );
		$this->assertSame( array( 1 ), wp_list_pluck( $groups[0]['items'], 'index' ) );
	}

	public function test_now_next_later_is_relative_to_today() {
		$items = array(
			$this->dated( '2026-12-31', 0 ),
			$this->dated( '2027-01-15', 1 ),
			$this->dated( '2027-04-01', 2 ),
			$this->dated( '2026-03-01', 3 ),
			array( 'target' => '', 'target_sort' => '', 'index' => 4 ),
		);

		$groups = Views::timeline_groups( $items, 'nownext', '2026-10-04' );

		$this->assertSame( array( 'Earlier', 'Now', 'Next', 'Later' ), $this->labels( $groups ) );
		$this->assertSame( array( 3 ), wp_list_pluck( $groups[0]['items'], 'index' ) );
		$this->assertSame( array( 0 ), wp_list_pluck( $groups[1]['items'], 'index' ) );
		$this->assertSame( array( 1 ), wp_list_pluck( $groups[2]['items'], 'index' ) );
		$this->assertSame( array( 2, 4 ), wp_list_pluck( $groups[3]['items'], 'index' ), 'Unscheduled items are "Later".' );

		// Next year's Q1 follows this year's Q4.
		$groups = Views::timeline_groups( array( $this->dated( '2027-01-02', 0 ) ), 'nownext', '2026-12-30' );
		$this->assertSame( array( 'Next' ), $this->labels( $groups ) );
	}

	// --- Rendering -------------------------------------------------------------------------------

	public function test_a_plain_board_is_the_board_and_loads_nothing_extra() {
		$id = $this->item( 'Plain item', 'Q4 2026' );

		$html = $this->board();

		$this->assertStringContainsString( 'id="nr-task-' . $id . '"', $html );
		$this->assertStringContainsString( 'class="nr-kanban-columns" data-nr-view="board"', $html );
		$this->assertStringNotContainsString( 'data-nr-views', $html );
		$this->assertStringNotContainsString( 'nr-view-switch', $html );
		$this->assertStringNotContainsString( 'nr-list-view', $html );
		$this->assertStringNotContainsString( 'class="nr-timeline"', $html );
		$this->assertFalse( wp_script_is( 'niroroadmap-views', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'niroroadmap-views', 'enqueued' ) );
	}

	public function test_the_switcher_puts_every_view_in_the_page_with_the_default_showing() {
		$id = $this->item( 'Switch item', '2026-11-05' );

		$html = $this->board( array( 'switcher' => 'yes', 'view' => 'list' ) );

		$this->assertStringContainsString( 'data-nr-views data-nr-default-view="list" data-nr-switcher', $html );
		$this->assertMatchesRegularExpression( '/class="nr-view-switch"[^>]*data-nr-switch hidden/', $html, 'Hidden until the script runs.' );
		foreach ( array( 'board', 'list', 'timeline' ) as $view ) {
			$this->assertStringContainsString( 'data-nr-view-btn="' . $view . '"', $html );
		}
		$this->assertMatchesRegularExpression( '/data-nr-view-btn="list" aria-pressed="true"/', $html );
		$this->assertMatchesRegularExpression( '/data-nr-view-btn="board" aria-pressed="false"/', $html );

		$this->assertMatchesRegularExpression( '/class="nr-kanban-columns" data-nr-view="board" hidden/', $html );
		$this->assertMatchesRegularExpression( '/class="nr-list-view" data-nr-view="list">/', $html );
		$this->assertMatchesRegularExpression( '/class="nr-timeline" data-nr-view="timeline" hidden/', $html );

		// The same item in all three, but the id only once, and each copy tells the popup which item it is.
		$this->assertSame( 1, substr_count( $html, 'id="nr-task-' . $id . '"' ) );
		$this->assertSame( 3, substr_count( $html, 'data-task="' . $id . '"' ) );

		$this->assertTrue( wp_script_is( 'niroroadmap-views', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'niroroadmap-views', 'enqueued' ) );
	}

	public function test_the_list_shows_the_same_items_as_the_board() {
		$a = $this->item( 'First idea', '2026-11-05', 'under-review' );
		$b = $this->item( 'Second idea', '', 'planned' );

		$html = $this->board( array( 'view' => 'list' ) );

		$this->assertStringNotContainsString( 'nr-kanban-columns', $html, 'Only the list is rendered.' );
		$this->assertMatchesRegularExpression( '/<tr class="nr-view-item" data-task="' . $a . '"/', $html );
		$this->assertMatchesRegularExpression( '/<tr class="nr-view-item" data-task="' . $b . '"/', $html );
		$this->assertStringContainsString( 'data-nr-sort-by="title"', $html );
		$this->assertStringContainsString( 'data-nr-sort-by="votes"', $html );
		$this->assertStringContainsString( 'data-nr-sort-by="target"', $html );
		$this->assertStringContainsString( 'First idea', $html );
		$this->assertStringContainsString( Fields::display_date( '2026-11-05' ), $html );
		$this->assertStringContainsString( 'data-target-sort="2026-11-05"', $html );
		$this->assertStringContainsString( 'data-stage-name="Under Review"', $html );
		$this->assertStringContainsString( 'nr-task-votes-count', $html );
	}

	public function test_list_columns_follow_the_settings_that_hide_things() {
		$this->item( 'Quiet idea', '2026-11-05' );
		$this->set( 'show_target', false );
		$this->set( 'show_vote_counts', false );
		$this->set( 'show_tags', false );

		$html = $this->board( array( 'view' => 'list' ) );

		$this->assertStringNotContainsString( 'nr-col-target', $html, 'A hidden target is not in the list.' );
		$this->assertStringNotContainsString( '2026', $html );
		$this->assertStringNotContainsString( 'nr-task-votes-count', $html );
		$this->assertStringNotContainsString( 'nr-col-tags', $html );
		$this->assertStringNotContainsString( 'data-target-sort', $html );
	}

	public function test_an_item_with_hidden_votes_shows_none_in_any_view() {
		$id = $this->item( 'Secret votes', '2026-11-05' );
		update_post_meta( $id, Fields::HIDE_VOTES, '1' );

		$html = $this->board( array( 'switcher' => 'yes' ) );

		// The board card, the list row and the timeline entry of this item, none of them with a count.
		$this->assertSame( 3, preg_match_all( '/data-task="' . $id . '".*?<\/(?:article|tr|li)>/s', $html, $copies ) );
		foreach ( $copies[0] as $copy ) {
			$this->assertStringNotContainsString( 'nr-task-votes', $copy );
		}
	}

	public function test_the_timeline_groups_items_and_lists_the_unscheduled_last() {
		$a = $this->item( 'Dated idea', '2026-11-05' );
		$b = $this->item( 'Quarter idea', 'Q1 2027' );
		$c = $this->item( 'Undated idea' );

		$html = $this->board( array( 'view' => 'timeline' ) );

		$this->assertStringNotContainsString( 'nr-kanban-columns', $html );
		$this->assertMatchesRegularExpression( '/Q4 2026.*Q1 2027.*Later \/ Unscheduled/s', $html );
		$this->assertMatchesRegularExpression( '/<li class="nr-view-item nr-timeline-item" data-task="' . $a . '"/', $html );
		$this->assertMatchesRegularExpression( '/<li class="nr-view-item nr-timeline-item" data-task="' . $b . '"/', $html );
		$this->assertMatchesRegularExpression( '/<li class="nr-view-item nr-timeline-item" data-task="' . $c . '"/', $html );
		$this->assertStringContainsString( 'Q1 2027', $html );
	}

	public function test_a_hidden_target_means_no_timeline_even_when_asked_for() {
		$this->item( 'Hidden target idea', '2026-11-05' );
		$this->set( 'show_target', false );

		$html = $this->board( array( 'view' => 'timeline', 'switcher' => 'yes' ) );

		$this->assertStringNotContainsString( 'class="nr-timeline"', $html );
		$this->assertStringNotContainsString( 'data-nr-view-btn="timeline"', $html );
		$this->assertStringNotContainsString( '2026', $html );
		$this->assertStringContainsString( 'data-nr-view="board"', $html );
	}

	public function test_targets_are_not_read_for_a_board_that_does_not_show_them() {
		$this->item( 'Lookup item', '2026-11-05' );
		$reads = 0;
		$count = static function ( $value, $post_id, $key ) use ( &$reads ) {
			if ( Fields::TARGET === $key ) {
				++$reads;
			}

			return $value;
		};
		add_filter( 'get_post_metadata', $count, 10, 3 );

		$this->board();
		$this->assertSame( 0, $reads, 'The board never shows a target.' );

		$this->board( array( 'view' => 'list' ) );
		$this->assertGreaterThan( 0, $reads );

		remove_filter( 'get_post_metadata', $count, 10 );
	}

	public function test_titles_are_escaped_in_every_view() {
		$this->item( 'Ben & <i>Jerry</i> idea', '2026-11-05' );

		$html = $this->board( array( 'switcher' => 'yes' ) );

		$this->assertStringNotContainsString( '<i>Jerry</i>', $html );
		$this->assertGreaterThanOrEqual( 3, substr_count( $html, 'Ben &amp; &lt;i&gt;Jerry&lt;/i&gt; idea' ) );
	}

	public function test_a_toolbar_board_gives_list_rows_what_the_toolbar_filters_by() {
		$id = $this->item( 'Filterable idea', '2026-11-05' );
		wp_set_object_terms( $id, array( 'payments' ), 'niroroadmap_tag' );

		$html = $this->board( array( 'view' => 'list', 'toolbar' => 'yes' ) );

		$this->assertMatchesRegularExpression( '/<tr class="nr-view-item" data-task="' . $id . '"[^>]*data-date="\d+"[^>]*data-product-ids=""[^>]*data-tag-slugs="payments"/', $html );
		$this->assertStringContainsString( 'data-nr-nomatch hidden', $html );

		wp_delete_term( get_term_by( 'slug', 'payments', 'niroroadmap_tag' )->term_id, 'niroroadmap_tag' );
	}

	public function test_the_shortcode_takes_the_view_options() {
		$this->item( 'Shortcode idea', '2026-11-05' );
		unset( $GLOBALS['wp_actions']['niroroadmap_modal_rendered'] );

		$html = do_shortcode( '[niroroadmap view="timeline" group="month" switcher="yes"]' );

		$this->assertStringContainsString( 'data-nr-default-view="timeline"', $html );
		$this->assertStringContainsString( 'data-nr-switcher', $html );
		$this->assertStringContainsString( 'November 2026', $html );
	}
}
