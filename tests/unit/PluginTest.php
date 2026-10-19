<?php declare( strict_types=1 );

namespace MerkushinTest\Inkmeter;

use Merkushin\Wpal\Service\Assets;
use Merkushin\Wpal\Service\Dates;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\Service\Localization;
use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\Service\Screen;
use Merkushin\Wpal\Service\UserSettings;
use Merkushin\Wpal\ServiceFactory;
use Merkushin\Inkmeter\Editor;
use Merkushin\Inkmeter\Goal;
use Merkushin\Inkmeter\GoalForm;
use Merkushin\Inkmeter\Pro\Pro;
use Merkushin\Inkmeter\Streak;
use Merkushin\Inkmeter\Plugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase {
	private const PLUGIN_FILE = '/plugins/inkmeter/inkmeter.php';

	private const PLUGIN_URL = 'https://example.com/wp-content/plugins/inkmeter/';

	/**
	 * @var Assets&MockObject
	 */
	private $assets;

	/**
	 * @var Screen&MockObject
	 */
	private $screen;

	/**
	 * @var Localization&MockObject
	 */
	private $localization;

	/**
	 * @var Dates&MockObject
	 */
	private $dates;

	/**
	 * @var UserSettings&MockObject
	 */
	private $user_settings;

	/**
	 * @var Streak&MockObject
	 */
	private $streak;

	/**
	 * @var GoalForm&MockObject
	 */
	private $goal_form;

	/**
	 * @var Pro&MockObject
	 */
	private $pro;

	/**
	 * @var Editor&MockObject
	 */
	private $editor;

	protected function setUp(): void {
		$this->assets       = $this->createMock( Assets::class );
		$this->screen       = $this->createMock( Screen::class );
		$this->localization = $this->createMock( Localization::class );
		$this->dates        = $this->createMock( Dates::class );
		$this->streak       = $this->createMock( Streak::class );
		$this->streak->method( 'goal' )->willReturn( Goal::daily() );
		$this->goal_form = $this->createMock( GoalForm::class );
		$this->pro       = $this->createMock( Pro::class );
		$this->editor    = $this->createMock( Editor::class );
		$this->goal_form->method( 'action_url' )->willReturn( 'https://example.com/wp-admin/admin-post.php' );

		$this->use_user_settings( [] );

		$plugins = $this->createMock( Plugins::class );
		$plugins->method( 'plugin_dir_url' )->with( self::PLUGIN_FILE )->willReturn( self::PLUGIN_URL );

		$options = $this->createMock( Options::class );
		$options->method( 'get_option' )->with( 'date_format' )->willReturn( 'd.m.Y' );

		ServiceFactory::set_custom_assets( $this->assets );
		ServiceFactory::set_custom_screen( $this->screen );
		ServiceFactory::set_custom_plugins( $plugins );
		ServiceFactory::set_custom_localization( $this->localization );
		ServiceFactory::set_custom_dates( $this->dates );
		ServiceFactory::set_custom_options( $options );
		ServiceFactory::set_custom_user_settings( $this->user_settings );
	}

	protected function tearDown(): void {
		ServiceFactory::set_custom_assets( null );
		ServiceFactory::set_custom_screen( null );
		ServiceFactory::set_custom_plugins( null );
		ServiceFactory::set_custom_localization( null );
		ServiceFactory::set_custom_dates( null );
		ServiceFactory::set_custom_options( null );
		ServiceFactory::set_custom_user_settings( null );
		ServiceFactory::set_custom_hooks( null );
	}

	public function testInit_Always_RegistersAdminHooksAndInitsStreakGoalFormAndPro(): void {
		$hooks = $this->createMock( Hooks::class );
		ServiceFactory::set_custom_hooks( $hooks );
		$plugin = new Plugin( self::PLUGIN_FILE, $this->streak, $this->goal_form, $this->pro, $this->editor );

		$registered = [];
		$record     = function ( string $hook, $callback ) use ( &$registered ): bool {
			$registered[ $hook ] = $callback;
			return true;
		};
		$hooks->method( 'add_action' )->willReturnCallback( $record );
		$hooks->method( 'add_filter' )->willReturnCallback( $record );
		$this->streak->expects( $this->once() )->method( 'init' );
		$this->goal_form->expects( $this->once() )->method( 'init' );
		$this->pro->expects( $this->once() )->method( 'init' );
		$this->editor->expects( $this->once() )->method( 'init' );

		$plugin->init();

		$this->assertEquals(
			[
				'admin_enqueue_scripts' => [ $plugin, 'enqueue_admin_assets' ],
				'all_admin_notices'     => [ $plugin, 'render_streak_panel' ],
				'screen_settings'       => [ $plugin, 'add_screen_option' ],
			],
			$registered
		);
	}

	public function testEnqueueAdminAssets_PostsScreen_EnqueuesAssets(): void {
		$this->screen->method( 'get_current_screen' )->willReturn( (object) [ 'id' => 'edit-post' ] );

		$this->assets
			->expects( $this->once() )
			->method( 'wp_enqueue_style' )
			->with( 'inkmeter-admin', self::PLUGIN_URL . 'assets/dist/styles/admin.css', [], Plugin::VERSION );
		$this->assets
			->expects( $this->once() )
			->method( 'wp_enqueue_script' )
			->with( 'inkmeter-admin', self::PLUGIN_URL . 'assets/dist/javascript/admin.js', [ 'utils' ], Plugin::VERSION, true );

		( new Plugin( self::PLUGIN_FILE, $this->streak, $this->goal_form, $this->pro, $this->editor ) )->enqueue_admin_assets();
	}

	/**
	 * @dataProvider provide_other_screens
	 *
	 * @param object|null $screen
	 */
	public function testEnqueueAdminAssets_OtherScreen_EnqueuesNothing( $screen ): void {
		$this->screen->method( 'get_current_screen' )->willReturn( $screen );

		$this->assets->expects( $this->never() )->method( 'wp_enqueue_style' );
		$this->assets->expects( $this->never() )->method( 'wp_enqueue_script' );

		( new Plugin( self::PLUGIN_FILE, $this->streak, $this->goal_form, $this->pro, $this->editor ) )->enqueue_admin_assets();
	}

	/**
	 * @dataProvider provide_other_screens
	 *
	 * @param object|null $screen
	 */
	public function testRenderStreakPanel_OtherScreen_RendersNothing( $screen ): void {
		$this->screen->method( 'get_current_screen' )->willReturn( $screen );

		$this->streak->expects( $this->never() )->method( 'get_summary' );

		$this->expectOutputString( '' );
		( new Plugin( self::PLUGIN_FILE, $this->streak, $this->goal_form, $this->pro, $this->editor ) )->render_streak_panel();
	}

	public function testRenderStreakPanel_PostsScreen_RendersLocalizedSummary(): void {
		$this->screen->method( 'get_current_screen' )->willReturn( (object) [ 'id' => 'edit-post' ] );
		$this->streak->method( 'get_summary' )->willReturn(
			[
				'streak'          => 1234,
				'last_post_date'  => '2026-03-01',
				'is_active_today' => false,
				'next_milestone'  => 1250,
			]
		);
		$this->localization->method( 'number_format_i18n' )->willReturnCallback(
			function ( $number ): string {
				return number_format( (float) $number, 0, ',', '.' );
			}
		);
		$this->dates
			->method( 'wp_date' )
			->willReturnCallback(
				function ( string $format, int $timestamp, \DateTimeZone $timezone ): string {
					return ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone )->format( $format );
				}
			);

		ob_start();
		( new Plugin( self::PLUGIN_FILE, $this->streak, $this->goal_form, $this->pro, $this->editor ) )->render_streak_panel();
		$text = trim( (string) preg_replace( '/\s+/', ' ', strip_tags( (string) ob_get_clean() ) ) );

		$this->assertStringContainsString( 'You are still alive, publish today to keep it going', $text );
		$this->assertStringContainsString( 'Current run 1.234 days', $text );
		$this->assertStringContainsString( 'Last published 01.03.2026', $text );
		$this->assertStringContainsString( 'Next milestone 1.250 days', $text );
		$this->assertStringContainsString( 'Needs a post today', $text );
		$this->assertStringContainsString( 'Next milestone 1.250 days 99%', $text );
	}

	public function testRenderStreakPanel_NoPosts_RendersEmptyState(): void {
		$this->screen->method( 'get_current_screen' )->willReturn( (object) [ 'id' => 'edit-post' ] );
		$this->streak->method( 'get_summary' )->willReturn(
			[
				'streak'          => 0,
				'last_post_date'  => null,
				'is_active_today' => false,
				'next_milestone'  => 3,
			]
		);
		$this->localization->method( 'number_format_i18n' )->willReturnCallback(
			function ( $number ): string {
				return (string) $number;
			}
		);
		$this->dates->method( 'current_time' )->willReturn( '2026-03-02' );

		ob_start();
		( new Plugin( self::PLUGIN_FILE, $this->streak, $this->goal_form, $this->pro, $this->editor ) )->render_streak_panel();
		$text = trim( (string) preg_replace( '/\s+/', ' ', strip_tags( (string) ob_get_clean() ) ) );

		$this->assertStringContainsString( 'Start your next streak', $text );
		$this->assertStringContainsString( 'Current run 0 days', $text );
		$this->assertStringContainsString( 'Last published No published posts yet', $text );
		$this->assertStringContainsString( 'Next milestone 3 days 0%', $text );
	}

	public function testRenderStreakPanel_PanelVisible_RendersWithoutHiddenAttribute(): void {
		$html = $this->render_panel();

		$this->assertStringContainsString( 'id="inkmeter-panel"', $html );
		$this->assertDoesNotMatchRegularExpression( '/<div id="inkmeter-panel"[^>]* hidden>/', $html );
	}

	public function testRenderStreakPanel_PanelHiddenInScreenOptions_RendersHidden(): void {
		$this->use_panel_setting( 'off' );

		$this->assertMatchesRegularExpression( '/<div id="inkmeter-panel"[^>]* hidden>/', $this->render_panel() );
	}

	public function testRenderStreakPanel_WeeklyGoal_CountsWeeksAndThisWeek(): void {
		$this->streak = $this->createMock( Streak::class );
		$this->streak->method( 'goal' )->willReturn( Goal::weekly( 3 ) );
		$text = $this->render_panel_text(
			[
				'streak'          => 6,
				'unit'            => 'week',
				'last_post_date'  => '2026-03-03',
				'is_active_today' => false,
				'is_goal_met'     => false,
				'goal_days'       => 3,
				'period_days'     => 1,
				'days_left'       => 4,
				'next_milestone'  => 8,
			]
		);

		$this->assertStringContainsString( 'Publish on 2 more days this week to keep it going', $text );
		$this->assertStringContainsString( 'Publish on 3 days a week', $text );
		$this->assertStringContainsString( 'Current run 6 weeks', $text );
		$this->assertStringContainsString( 'This week 1 of 3 days', $text );
		$this->assertStringContainsString( 'Next milestone 8 weeks', $text );
		$this->assertStringContainsString( '6 weeks', $text );
		$this->assertStringContainsString( '2 more days this week', $text );
	}

	public function testRenderStreakPanel_WeeklyGoalMet_SaysSo(): void {
		$this->streak = $this->createMock( Streak::class );
		$this->streak->method( 'goal' )->willReturn( Goal::weekly( 2 ) );
		$text = $this->render_panel_text(
			[
				'streak'          => 3,
				'unit'            => 'week',
				'last_post_date'  => '2026-03-03',
				'is_active_today' => false,
				'is_goal_met'     => true,
				'goal_days'       => 2,
				'period_days'     => 2,
				'days_left'       => 4,
				'next_milestone'  => 4,
			]
		);

		$this->assertStringContainsString( 'You met your goal this week', $text );
		$this->assertStringContainsString( 'Goal met this week', $text );
	}

	public function testRenderStreakPanel_WeeklyGoalOutOfReach_SaysANewStreakStartsNextWeek(): void {
		$this->streak = $this->createMock( Streak::class );
		$this->streak->method( 'goal' )->willReturn( Goal::weekly( 5 ) );
		$text = $this->render_panel_text(
			[
				'streak'          => 2,
				'unit'            => 'week',
				'last_post_date'  => '2026-03-03',
				'is_active_today' => false,
				'is_goal_met'     => false,
				'goal_days'       => 5,
				'period_days'     => 1,
				'days_left'       => 2,
				'next_milestone'  => 4,
			]
		);

		$this->assertStringContainsString( 'Not enough days are left to meet this week\'s goal.', $text );
		$this->assertStringContainsString( 'Next week is a fresh start', $text );
		$this->assertStringNotContainsString( 'more days this week', $text );
	}

	public function testRenderStreakPanel_NoStreakAndOutOfReach_SaysNextWeekNotStart(): void {
		$this->streak = $this->createMock( Streak::class );
		$this->streak->method( 'goal' )->willReturn( Goal::weekly( 6 ) );
		$text = $this->render_panel_text(
			[
				'streak'          => 0,
				'unit'            => 'week',
				'last_post_date'  => '2026-03-03',
				'is_active_today' => false,
				'is_goal_met'     => false,
				'goal_days'       => 6,
				'period_days'     => 3,
				'days_left'       => 2,
				'next_milestone'  => 2,
			]
		);

		$this->assertStringContainsString( 'A new streak starts next week.', $text );
		$this->assertStringNotContainsString( 'Start your next streak', $text );
	}

	public function testRenderStreakPanel_AdminCanChangeGoal_ShowsTheForm(): void {
		$this->goal_form->method( 'can_change' )->willReturn( true );

		$html = $this->render_panel();

		$this->assertStringContainsString( 'Change goal', $html );
		$this->assertStringContainsString( 'action="https://example.com/wp-admin/admin-post.php"', $html );
		$this->assertStringContainsString( 'name="action" value="inkmeter_goal"', $html );
		$this->assertStringContainsString( "<option value=\"daily\" selected='selected'>Publish every day</option>", $html );
		$this->assertStringContainsString( '<option value="weekly-6">Publish on 6 days a week</option>', $html );
	}

	public function testRenderStreakPanel_OtherUsers_SeeTheGoalWithoutTheForm(): void {
		$this->goal_form->method( 'can_change' )->willReturn( false );

		$html = $this->render_panel();

		$this->assertStringContainsString( 'Publish every day', $html );
		$this->assertStringNotContainsString( 'Change goal', $html );
	}

	public function testRenderStreakPanel_RemindersAvailable_LinksToSettings(): void {
		$this->pro->method( 'reminders_url' )->willReturn( 'https://example.com/wp-admin/options-general.php?page=inkmeter' );

		$html = $this->render_panel();

		$this->assertStringContainsString( 'href="https://example.com/wp-admin/options-general.php?page=inkmeter"', $html );
		$this->assertStringContainsString( 'Inkmeter Pro: get an email before your streak breaks', $html );
		$this->assertStringContainsString( 'id="inkmeter-pro-link-dismiss"', $html );
	}

	public function testRenderStreakPanel_ProLinkDismissed_HasNoLink(): void {
		$this->pro->method( 'reminders_url' )->willReturn( 'https://example.com/wp-admin/options-general.php?page=inkmeter' );
		$this->use_user_settings( [ Plugin::PRO_LINK_SETTING => 'off' ] );
		ServiceFactory::set_custom_user_settings( $this->user_settings );

		$this->assertStringNotContainsString( 'inkmeter-panel__reminders', $this->render_panel() );
	}

	public function testRenderStreakPanel_RemindersSetUpOrNotAllowed_HasNoLink(): void {
		$this->pro->method( 'reminders_url' )->willReturn( null );

		$this->assertStringNotContainsString( 'inkmeter-panel__reminders', $this->render_panel() );
	}

	public function testRenderStreakPanel_FreezeSavedYesterday_SaysSoWithFreezesLeft(): void {
		$this->pro->method( 'freezes_left' )->willReturn( 1 );
		$text = $this->render_panel_text(
			[
				'streak'          => 5,
				'unit'            => 'day',
				'last_post_date'  => '2026-03-03',
				'is_active_today' => false,
				'is_goal_met'     => false,
				'goal_days'       => 1,
				'period_days'     => 0,
				'days_left'       => 1,
				'saved_by_freeze' => true,
				'next_milestone'  => 7,
			]
		);

		$this->assertStringContainsString( 'A streak freeze saved yesterday. 1 streak freeze left this month', $text );
		$this->assertStringContainsString( 'Needs a post today 1 freeze left', $text, 'the bar counts the freezes too' );
	}

	public function testRenderStreakPanel_WithoutFreezes_ShowsNoFreezeLine(): void {
		$this->pro->method( 'freezes_left' )->willReturn( null );

		$html = $this->render_panel();

		$this->assertStringNotContainsString( 'inkmeter-panel__freezes', $html );
		$this->assertStringNotContainsString( 'inkmeter-panel__freeze-count', $html );
	}

	public function testRenderStreakPanel_DetailsByDefault_RendersThemClosed(): void {
		$html = $this->render_panel();

		$this->assertStringContainsString( 'id="inkmeter-details-toggle" aria-expanded="false"', $html );
		$this->assertStringContainsString( 'id="inkmeter-panel-details" hidden>', $html );
	}

	public function testRenderStreakPanel_DetailsKeptOpen_RendersThemOpen(): void {
		$this->use_user_settings( [ Plugin::DETAILS_SETTING => 'open' ] );
		ServiceFactory::set_custom_user_settings( $this->user_settings );

		$html = $this->render_panel();

		$this->assertStringContainsString( 'id="inkmeter-details-toggle" aria-expanded="true"', $html );
		$this->assertStringContainsString( 'id="inkmeter-panel-details">', $html );
	}

	public function testRenderStreakPanel_RecentDays_ShowsTheLast14DaysEndingToday(): void {
		$this->dates->method( 'current_time' )->willReturn( '2026-03-05' );
		$this->dates->method( 'wp_date' )->willReturnCallback(
			function ( string $format, int $timestamp ): string {
				return gmdate( 'M j', $timestamp );
			}
		);
		$html = $this->render_panel_with( [ 'recent_days' => [ '2026-02-20', '2026-03-04' ] ] );

		$this->assertSame( 1, preg_match( '/<div class="inkmeter-panel__recent-days"[^>]*>(.*?)<\/div>/s', $html, $recent ) );
		$this->assertSame( 14, substr_count( $recent[1], 'inkmeter-history__day' ) );
		$this->assertStringContainsString( 'class="inkmeter-history__day is-published" title="Feb 20: published"', $recent[1], 'the first of the 14 days' );
		$this->assertStringContainsString( 'class="inkmeter-history__day is-published" title="Mar 4: published"', $recent[1] );
		$this->assertStringContainsString( 'class="inkmeter-history__day is-empty is-today" title="Mar 5: no post"', $recent[1] );
		$this->assertStringNotContainsString( 'Feb 19', $recent[1] );
	}

	public function testRenderStreakPanel_History_ShowsThePastYearWithoutPro(): void {
		$this->dates->method( 'current_time' )->willReturn( '2026-03-05' );
		$this->dates->method( 'wp_date' )->willReturn( 'Mar 2' );
		$html = $this->render_panel_with(
			[
				'recent_days' => [ '2025-04-01', '2026-03-02', '2026-03-03' ],
				'frozen'      => [ '2026-03-04' ],
			]
		);

		$this->assertSame( 53 * 7, substr_count( $html, 'inkmeter-history__day is-' ) - 14 - 3, '53 weeks of days, plus the last 14 days and the legend' );
		$this->assertStringContainsString( 'Past year', $html );
		$this->assertStringContainsString( 'Published on 3 of 368 days', $html );
		$this->assertStringContainsString( 'class="inkmeter-history__day is-frozen" title="Mar 2: covered by a streak freeze"', $html );
		$this->assertStringContainsString( 'Streak freeze', $html );
		$this->assertStringNotContainsString( 'Counting posts on all your connected sites', $html );
	}

	public function testRenderStreakPanel_CountingDrafts_SaysWrote(): void {
		$this->streak->method( 'counts_drafts' )->willReturn( true );
		$this->streak->method( 'goal' )->willReturn( Goal::daily() );
		$this->goal_form->method( 'can_change' )->willReturn( true );
		$this->dates->method( 'current_time' )->willReturn( '2026-03-03' );
		$this->dates->method( 'wp_date' )->willReturn( 'Mar 3' );

		$html = $this->render_panel_with(
			[
				'is_active_today' => true,
				'is_goal_met'     => true,
				'recent_days'     => [ '2026-03-03' ],
				'counts_drafts'   => true,
			]
		);

		foreach ( [ 'Write every day', 'Wrote today', 'Last writing day', 'Wrote on 1 of', 'Mar 3: wrote', 'No writing' ] as $expected ) {
			$this->assertStringContainsString( $expected, $html );
		}
		$this->assertStringContainsString( 'name="inkmeter_count_drafts" value="1" checked=\'checked\'', $html );
		$this->assertStringNotContainsString( 'Published today', $html );
	}

	public function testRenderStreakPanel_AcrossSites_SaysSo(): void {
		$this->dates->method( 'current_time' )->willReturn( '2026-03-05' );

		$html = $this->render_panel_with( [ 'across_sites' => true ] );

		$this->assertStringContainsString( 'Counting posts on all your connected sites', $html );
	}

	/**
	 * @param array<string, mixed> $fields Added to a daily summary.
	 */
	private function render_panel_with( array $fields ): string {
		$this->screen->method( 'get_current_screen' )->willReturn( (object) [ 'id' => 'edit-post' ] );
		$this->streak->method( 'week_start' )->willReturn( 1 );
		$this->streak->method( 'get_summary' )->willReturn(
			array_merge(
				[
					'streak'          => 2,
					'last_post_date'  => '2026-03-03',
					'is_active_today' => false,
					'next_milestone'  => 3,
				],
				$fields
			)
		);
		$this->localization->method( 'number_format_i18n' )->willReturnCallback(
			function ( $number ): string {
				return (string) $number;
			}
		);

		ob_start();
		( new Plugin( self::PLUGIN_FILE, $this->streak, $this->goal_form, $this->pro ) )->render_streak_panel();

		return (string) ob_get_clean();
	}

	public function testAddScreenOption_PostsScreen_AppendsCheckedToggle(): void {
		$settings = ( new Plugin( self::PLUGIN_FILE, $this->streak, $this->goal_form, $this->pro, $this->editor ) )->add_screen_option( '<p>core</p>', (object) [ 'id' => 'edit-post' ] );

		$this->assertStringStartsWith( '<p>core</p>', $settings );
		$this->assertStringContainsString( 'id="inkmeter-panel-toggle"', $settings );
		$this->assertStringContainsString( "checked='checked'", $settings );
		$this->assertStringContainsString( 'Writing streak panel', $settings );
	}

	public function testAddScreenOption_PanelHidden_AppendsUncheckedToggle(): void {
		$this->use_panel_setting( 'off' );

		$settings = ( new Plugin( self::PLUGIN_FILE, $this->streak, $this->goal_form, $this->pro, $this->editor ) )->add_screen_option( '', (object) [ 'id' => 'edit-post' ] );

		$this->assertStringContainsString( 'id="inkmeter-panel-toggle"', $settings );
		$this->assertStringNotContainsString( 'checked', $settings );
	}

	/**
	 * @dataProvider provide_other_screens
	 *
	 * @param object|null $screen
	 */
	public function testAddScreenOption_OtherScreen_KeepsSettingsUnchanged( $screen ): void {
		$settings = ( new Plugin( self::PLUGIN_FILE, $this->streak, $this->goal_form, $this->pro, $this->editor ) )->add_screen_option( '<p>core</p>', $screen );

		$this->assertSame( '<p>core</p>', $settings );
	}

	private function use_panel_setting( string $value ): void {
		$this->use_user_settings( [ Plugin::PANEL_SETTING => $value ] );
		ServiceFactory::set_custom_user_settings( $this->user_settings );
	}

	/**
	 * @param array<string, string> $values User settings that aren't at their default.
	 */
	private function use_user_settings( array $values ): void {
		$this->user_settings = $this->createMock( UserSettings::class );
		$this->user_settings->method( 'get_user_setting' )->willReturnCallback(
			static function ( string $name, $default_value ) use ( $values ) {
				return $values[ $name ] ?? $default_value;
			}
		);
	}

	private function render_panel(): string {
		$this->screen->method( 'get_current_screen' )->willReturn( (object) [ 'id' => 'edit-post' ] );
		$this->streak->method( 'get_summary' )->willReturn(
			[
				'streak'          => 2,
				'last_post_date'  => null,
				'is_active_today' => true,
				'next_milestone'  => 3,
			]
		);
		$this->localization->method( 'number_format_i18n' )->willReturnCallback(
			function ( $number ): string {
				return (string) $number;
			}
		);

		ob_start();
		( new Plugin( self::PLUGIN_FILE, $this->streak, $this->goal_form, $this->pro, $this->editor ) )->render_streak_panel();

		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, mixed> $summary
	 */
	private function render_panel_text( array $summary ): string {
		$this->screen->method( 'get_current_screen' )->willReturn( (object) [ 'id' => 'edit-post' ] );
		$this->streak->method( 'get_summary' )->willReturn( $summary );
		$this->localization->method( 'number_format_i18n' )->willReturnCallback(
			function ( $number ): string {
				return (string) $number;
			}
		);
		$this->dates->method( 'wp_date' )->willReturn( 'March 3, 2026' );

		ob_start();
		( new Plugin( self::PLUGIN_FILE, $this->streak, $this->goal_form, $this->pro, $this->editor ) )->render_streak_panel();

		return trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( (string) ob_get_clean() ), ENT_QUOTES ) ) );
	}

	public function provide_other_screens(): array {
		return [
			'pages list' => [ (object) [ 'id' => 'edit-page' ] ],
			'dashboard'  => [ (object) [ 'id' => 'dashboard' ] ],
			'no screen'  => [ null ],
		];
	}
}
