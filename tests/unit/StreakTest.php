<?php declare( strict_types=1 );

namespace MerkushinTest\Inkmeter;

use Merkushin\Wpal\Service\Dates;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\Service\PostTypes;
use Merkushin\Wpal\Service\Transient;
use Merkushin\Wpal\ServiceFactory;
use Merkushin\Inkmeter\Goal;
use Merkushin\Inkmeter\GoalSettings;
use Merkushin\Inkmeter\PublishedPostDates;
use Merkushin\Inkmeter\Streak;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StreakTest extends TestCase {
	/**
	 * @var Transient&MockObject
	 */
	private $transient;

	/**
	 * @var PostTypes&MockObject
	 */
	private $post_types;

	/**
	 * @var PublishedPostDates&MockObject
	 */
	private $post_dates;

	/**
	 * @var GoalSettings&MockObject
	 */
	private $goal_settings;

	/**
	 * @var Hooks&MockObject
	 */
	private $hooks;

	/**
	 * @var string[] What the frozen-periods filter returns.
	 */
	private $frozen = [];

	/**
	 * @var string[]|null What the account-days filter returns.
	 */
	private $account_days = null;

	protected function setUp(): void {
		$this->transient  = $this->createMock( Transient::class );
		$this->post_types = $this->createMock( PostTypes::class );
		$this->post_dates = $this->createMock( PublishedPostDates::class );

		$this->goal_settings = $this->createMock( GoalSettings::class );
		$this->goal_settings->method( 'week_start' )->willReturn( 1 );

		// No freezes or account days unless a test adds them, as without Inkmeter Pro.
		$this->hooks = $this->createMock( Hooks::class );
		$this->hooks->method( 'apply_filters' )->willReturnCallback(
			function ( string $hook, $value ) {
				if ( Streak::FROZEN_FILTER === $hook ) {
					return $this->frozen;
				}
				return Streak::ACCOUNT_DAYS_FILTER === $hook ? $this->account_days : $value;
			}
		);
		ServiceFactory::set_custom_hooks( $this->hooks );

		$dates = $this->createMock( Dates::class );
		$dates->method( 'current_time' )->with( 'Y-m-d' )->willReturn( '2026-03-02' );

		ServiceFactory::set_custom_transient( $this->transient );
		ServiceFactory::set_custom_post_types( $this->post_types );
		ServiceFactory::set_custom_dates( $dates );
	}

	protected function tearDown(): void {
		ServiceFactory::set_custom_transient( null );
		ServiceFactory::set_custom_post_types( null );
		ServiceFactory::set_custom_dates( null );
		ServiceFactory::set_custom_hooks( null );
	}

	public function testInit_Always_ClearsCacheOnPostChanges(): void {
		$hooks = $this->createMock( Hooks::class );
		ServiceFactory::set_custom_hooks( $hooks );
		$streak = new Streak( $this->post_dates, null, $this->goal_settings );

		$registered = [];
		$hooks->method( 'add_action' )->willReturnCallback(
			function ( string $hook, $callback ) use ( &$registered ): bool {
				$registered[ $hook ] = $callback;
				return true;
			}
		);

		$streak->init();

		$this->assertEquals(
			[
				'save_post'   => [ $streak, 'clear_cache' ],
				'delete_post' => [ $streak, 'clear_cache' ],
			],
			$registered
		);
	}

	public function testGetSummary_CachedToday_ReturnsCachedSummary(): void {
		$cached = [
			'streak'          => 5,
			'last_post_date'  => '2026-03-02',
			'is_active_today' => true,
			'next_milestone'  => 7,
		];
		$this->goal_settings->method( 'goal' )->willReturn( Goal::daily() );
		$this->transient->method( 'get_transient' )->willReturn(
			[
				'key'     => '2026-03-02|daily|1|d41d8cd98f00b204e9800998ecf8427e|-',
				'summary' => $cached,
			]
		);

		$this->post_dates->expects( $this->never() )->method( 'get_dates' );

		$this->assertSame( $cached, ( new Streak( $this->post_dates, null, $this->goal_settings ) )->get_summary() );
	}

	public function testGetSummary_CachedYesterday_Recalculates(): void {
		$stale = [
			'streak'          => 5,
			'last_post_date'  => '2026-03-01',
			'is_active_today' => true,
			'next_milestone'  => 7,
		];
		$this->goal_settings->method( 'goal' )->willReturn( Goal::daily() );
		$this->transient->method( 'get_transient' )->willReturn(
			[
				'key'     => '2026-03-01|daily|1|d41d8cd98f00b204e9800998ecf8427e',
				'summary' => $stale,
			]
		);
		$this->post_dates->method( 'get_dates' )->willReturn( [ '2026-03-01' ] );

		$summary = ( new Streak( $this->post_dates, null, $this->goal_settings ) )->get_summary();

		$this->assertSame( 1, $summary['streak'] );
		$this->assertFalse( $summary['is_active_today'] );
	}

	public function testGetSummary_NotCached_StoresSummaryWithToday(): void {
		$this->goal_settings->method( 'goal' )->willReturn( Goal::daily() );
		$this->transient->method( 'get_transient' )->willReturn( false );
		$this->post_dates->method( 'get_dates' )->willReturn( [ '2026-03-02', '2026-03-01' ] );

		$this->transient
			->expects( $this->once() )
			->method( 'set_transient' )
			->with(
				Streak::TRANSIENT_KEY,
				[
					'key'     => '2026-03-02|daily|1|d41d8cd98f00b204e9800998ecf8427e|-',
					'summary' => [
						'streak'          => 2,
						'unit'            => 'day',
						'last_post_date'  => '2026-03-02',
						'is_active_today' => true,
						'is_goal_met'     => true,
						'goal_days'       => 1,
						'period_days'     => 1,
						'days_left'       => 1,
						'saved_by_freeze' => false,
						'next_milestone'  => 3,
						'recent_days'     => [ '2026-03-02', '2026-03-01' ],
						'frozen'          => [],
						'across_sites'    => false,
					],
				],
				$this->greaterThan( 0 )
			)
			->willReturn( true );

		( new Streak( $this->post_dates, null, $this->goal_settings ) )->get_summary();
	}

	public function testGetSummary_GoalChangedSinceCached_Recalculates(): void {
		$this->goal_settings->method( 'goal' )->willReturn( Goal::weekly( 2 ) );
		$this->transient->method( 'get_transient' )->willReturn(
			[
				'key'     => '2026-03-02|daily|1|d41d8cd98f00b204e9800998ecf8427e',
				'summary' => [ 'streak' => 99 ],
			]
		);
		// Monday 2026-03-02: last week (Feb 23 to Mar 1) had two days with a post.
		$this->post_dates->method( 'get_dates' )->willReturn( [ '2026-02-24', '2026-02-26' ] );

		$summary = ( new Streak( $this->post_dates, null, $this->goal_settings ) )->get_summary();

		$this->assertSame( [ 1, 'week', false ], [ $summary['streak'], $summary['unit'], $summary['is_goal_met'] ] );
	}

	public function testGetSummary_FrozenPeriodsFiltered_CountsThem(): void {
		$this->goal_settings->method( 'goal' )->willReturn( Goal::daily() );
		$this->transient->method( 'get_transient' )->willReturn( false );
		// Monday 2026-03-02: a freeze covered yesterday, Sunday.
		$this->post_dates->method( 'get_dates' )->willReturn( [ '2026-02-27', '2026-02-28' ] );
		$this->frozen = [ '2026-03-01' ];

		$summary = ( new Streak( $this->post_dates, null, $this->goal_settings ) )->get_summary();

		$this->assertSame( [ 2, true ], [ $summary['streak'], $summary['saved_by_freeze'] ] );
	}

	public function testGetSummary_FreezesChangedSinceCached_Recalculates(): void {
		$this->goal_settings->method( 'goal' )->willReturn( Goal::daily() );
		$this->transient->method( 'get_transient' )->willReturn(
			[
				'key'     => '2026-03-02|daily|1|d41d8cd98f00b204e9800998ecf8427e',
				'summary' => [ 'streak' => 0 ],
			]
		);
		$this->post_dates->method( 'get_dates' )->willReturn( [ '2026-02-28' ] );
		$this->frozen = [ '2026-03-01' ];

		$summary = ( new Streak( $this->post_dates, null, $this->goal_settings ) )->get_summary();

		$this->assertSame( 1, $summary['streak'] );
	}

	public function testGetSummary_AccountDays_CountsTheOtherSites(): void {
		$this->goal_settings->method( 'goal' )->willReturn( Goal::daily() );
		$this->transient->method( 'get_transient' )->willReturn( false );
		// Monday 2026-03-02: this site published on Saturday and today, another site on Sunday.
		$this->post_dates->method( 'get_dates' )->willReturn( [ '2026-03-02', '2026-02-28' ] );
		$this->account_days = [ '2026-02-28', '2026-03-01' ];

		$summary = ( new Streak( $this->post_dates, null, $this->goal_settings ) )->get_summary();

		$this->assertSame( 3, $summary['streak'] );
		$this->assertTrue( $summary['across_sites'] );
		$this->assertEqualsCanonicalizing( [ '2026-03-02', '2026-02-28', '2026-03-01' ], $summary['recent_days'] );
	}

	public function testGetSummary_AccountDaysChangedSinceCached_Recalculates(): void {
		$this->goal_settings->method( 'goal' )->willReturn( Goal::daily() );
		$this->transient->method( 'get_transient' )->willReturn(
			[
				'key'     => '2026-03-02|daily|1|d41d8cd98f00b204e9800998ecf8427e|-',
				'summary' => [ 'streak' => 1 ],
			]
		);
		$this->post_dates->method( 'get_dates' )->willReturn( [ '2026-03-02' ] );
		$this->account_days = [ '2026-03-01' ];

		$summary = ( new Streak( $this->post_dates, null, $this->goal_settings ) )->get_summary();

		$this->assertSame( 2, $summary['streak'] );
	}

	public function testSaveGoal_Always_SavesItAndClearsTheCache(): void {
		$goal = Goal::weekly( 3 );
		$this->goal_settings->expects( $this->once() )->method( 'save' )->with( $goal );
		$this->transient->expects( $this->once() )->method( 'delete_transient' )->with( Streak::TRANSIENT_KEY );

		( new Streak( $this->post_dates, null, $this->goal_settings ) )->save_goal( $goal );
	}

	public function testClearCache_Post_DeletesTransient(): void {
		$this->post_types->method( 'get_post_type' )->with( 42 )->willReturn( 'post' );

		$this->transient->expects( $this->once() )->method( 'delete_transient' )->with( Streak::TRANSIENT_KEY );

		( new Streak( $this->post_dates, null, $this->goal_settings ) )->clear_cache( 42 );
	}

	public function testClearCache_OtherPostType_KeepsTransient(): void {
		$this->post_types->method( 'get_post_type' )->willReturn( 'page' );

		$this->transient->expects( $this->never() )->method( 'delete_transient' );

		( new Streak( $this->post_dates, null, $this->goal_settings ) )->clear_cache( 42 );
	}
}
