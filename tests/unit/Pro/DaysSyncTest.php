<?php declare( strict_types=1 );

namespace MerkushinTest\Inkmeter\Pro;

use Merkushin\Wpal\Service\Cron;
use Merkushin\Wpal\Service\Dates;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\Service\PostTypes;
use Merkushin\Wpal\ServiceFactory;
use Merkushin\Inkmeter\Pro\Api;
use Merkushin\Inkmeter\Pro\Connection;
use Merkushin\Inkmeter\Pro\DaysSync;
use Merkushin\Inkmeter\Goal;
use Merkushin\Inkmeter\DraftDates;
use Merkushin\Inkmeter\GoalSettings;
use Merkushin\Inkmeter\PublishedPostDates;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DaysSyncTest extends TestCase {
	use OptionsStore;

	/**
	 * @var Cron&MockObject
	 */
	private $cron;

	/**
	 * @var PostTypes&MockObject
	 */
	private $post_types;

	/**
	 * @var Api&MockObject
	 */
	private $api;

	/**
	 * @var PublishedPostDates&MockObject
	 */
	private $post_dates;

	/**
	 * @var Connection
	 */
	private $connection;

	/**
	 * @var GoalSettings&MockObject
	 */
	private $goal_settings;

	protected function setUp(): void {
		$this->cron       = $this->createMock( Cron::class );
		$this->post_types = $this->createMock( PostTypes::class );
		$this->api        = $this->createMock( Api::class );
		$this->post_dates = $this->createMock( PublishedPostDates::class );

		$this->goal_settings = $this->createMock( GoalSettings::class );
		$this->goal_settings->method( 'goal' )->willReturn( Goal::weekly( 3 ) );
		$this->goal_settings->method( 'week_start' )->willReturn( 0 );
		$dates = $this->createMock( Dates::class );
		$dates->method( 'wp_timezone_string' )->willReturn( 'America/Mexico_City' );

		ServiceFactory::set_custom_hooks( $this->createMock( Hooks::class ) );
		ServiceFactory::set_custom_cron( $this->cron );
		ServiceFactory::set_custom_post_types( $this->post_types );
		ServiceFactory::set_custom_dates( $dates );
		ServiceFactory::set_custom_options( $this->create_options_store( $this ) );

		$this->connection = new Connection();
	}

	protected function tearDown(): void {
		ServiceFactory::set_custom_hooks( null );
		ServiceFactory::set_custom_cron( null );
		ServiceFactory::set_custom_post_types( null );
		ServiceFactory::set_custom_dates( null );
		ServiceFactory::set_custom_options( null );
	}

	public function testSchedule_ConnectedAndPostChanged_SchedulesSyncInAMinute(): void {
		$this->connection->connect( 'sfs_token', 'writer@example.com', [] );
		$this->post_types->method( 'get_post_type' )->with( 12 )->willReturn( 'post' );

		$this->cron
			->expects( $this->once() )
			->method( 'wp_schedule_single_event' )
			->with(
				$this->callback(
					function ( int $timestamp ): bool {
						return abs( $timestamp - ( time() + 60 ) ) <= 2;
					}
				),
				DaysSync::HOOK
			);

		$this->create_sync()->schedule( '12' );
	}

	public function testSchedule_NotConnected_DoesNothing(): void {
		$this->post_types->method( 'get_post_type' )->willReturn( 'post' );
		$this->cron->expects( $this->never() )->method( 'wp_schedule_single_event' );

		$this->create_sync()->schedule( 12 );
	}

	public function testSchedule_PageChanged_DoesNothing(): void {
		$this->connection->connect( 'sfs_token', 'writer@example.com', [] );
		$this->post_types->method( 'get_post_type' )->willReturn( 'page' );
		$this->cron->expects( $this->never() )->method( 'wp_schedule_single_event' );

		$this->create_sync()->schedule( 12 );
	}

	public function testSync_Connected_SendsAllPublishedDaysAndTimezone(): void {
		$this->connection->connect( 'sfs_token', 'writer@example.com', [] );
		$this->post_dates->method( 'get_dates' )->willReturn( [ '2026-10-09', '2026-10-08' ] );
		$this->api
			->expects( $this->once() )
			->method( 'request' )
			->with(
				'PUT',
				'/v1/days',
				'sfs_token',
				[
					'days'           => [ '2026-10-09', '2026-10-08' ],
					'timezone'       => 'America/Mexico_City',
					'goal'           => [
						'type' => 'weekly',
						'days' => 3,
					],
					'week_starts_on' => 0,
					'counts_drafts'  => false,
				]
			)
			->willReturn(
				[
					'status' => 200,
					'data'   => [
						'current'      => 4,
						'unit'         => 'week',
						'frozen'       => [ '2026-09-27' ],
						'freezes_left' => 1,
					],
				]
			);

		$this->create_sync()->sync();

		$this->assertEqualsWithDelta( time(), $this->connection->last_synced_at(), 2 );
		$this->assertSame( [ '2026-09-27' ], $this->connection->frozen( 'week' ) );
		$this->assertSame( [], $this->connection->frozen( 'day' ), 'freezes are for the unit the server counted in' );
		$this->assertSame( 1, $this->connection->freezes_left() );
	}

	public function testSync_NothingPublished_SendsAnEmptyList(): void {
		$this->connection->connect( 'sfs_token', 'writer@example.com', [] );
		$this->post_dates->method( 'get_dates' )->willReturn( [] );
		$this->api
			->expects( $this->once() )
			->method( 'request' )
			->with( 'PUT', '/v1/days', 'sfs_token', $this->callback( fn( array $body ): bool => [] === $body['days'] ) )
			->willReturn(
				[
					'status' => 200,
					'data'   => [],
				]
			);

		$this->create_sync()->sync();
	}

	public function testSync_NotConnected_SendsNothing(): void {
		$this->api->expects( $this->never() )->method( 'request' );

		$this->create_sync()->sync();
	}

	public function testSync_TokenRevoked_ForgetsConnectionAndStopsSyncing(): void {
		$this->connection->connect( 'sfs_token', 'writer@example.com', [] );
		$this->post_dates->method( 'get_dates' )->willReturn( [] );
		$this->api->method( 'request' )->willReturn(
			[
				'status' => 401,
				'data'   => [],
			]
		);
		$cleared = [];
		$this->cron->method( 'wp_clear_scheduled_hook' )->willReturnCallback(
			function ( string $hook ) use ( &$cleared ): int {
				$cleared[] = $hook;
				return 1;
			}
		);

		$this->create_sync()->sync();

		$this->assertFalse( $this->connection->is_connected() );
		$this->assertSame( [ DaysSync::HOOK, DaysSync::DAILY_HOOK ], $cleared );
	}

	public function testSync_Unreachable_KeepsConnectionForTheNextTry(): void {
		$this->connection->connect( 'sfs_token', 'writer@example.com', [] );
		$this->post_dates->method( 'get_dates' )->willReturn( [] );
		$this->api->method( 'request' )->willReturn( null );

		$this->create_sync()->sync();

		$this->assertTrue( $this->connection->is_connected() );
		$this->assertNull( $this->connection->last_synced_at() );
	}

	public function testStart_NoDailySync_SyncsNowAndSchedulesDaily(): void {
		$this->connection->connect( 'sfs_token', 'writer@example.com', [] );
		$this->post_dates->method( 'get_dates' )->willReturn( [] );
		$this->api->expects( $this->once() )->method( 'request' )->willReturn(
			[
				'status' => 200,
				'data'   => [],
			]
		);
		$this->cron->method( 'wp_next_scheduled' )->with( DaysSync::DAILY_HOOK )->willReturn( false );
		$this->cron->expects( $this->once() )->method( 'wp_schedule_event' )->with( $this->anything(), 'daily', DaysSync::DAILY_HOOK );

		$this->create_sync()->start();
	}

	public function testStart_DailySyncExists_DoesNotScheduleAnother(): void {
		$this->cron->method( 'wp_next_scheduled' )->willReturn( time() + 3600 );
		$this->cron->expects( $this->never() )->method( 'wp_schedule_event' );

		$this->create_sync()->start();
	}

	public function testInit_GoalChanges_ScheduleASync(): void {
		$hooks = $this->createMock( Hooks::class );
		ServiceFactory::set_custom_hooks( $hooks );
		$sync = $this->create_sync();

		$registered = [];
		$hooks->method( 'add_action' )->willReturnCallback(
			function ( string $hook, $callback ) use ( &$registered ): bool {
				$registered[ $hook ] = $callback;
				return true;
			}
		);

		$sync->init();

		$this->assertSame( [ $sync, 'schedule_soon' ], $registered['add_option_inkmeter_goal'] );
		$this->assertSame( [ $sync, 'schedule_soon' ], $registered['update_option_inkmeter_goal'] );
		$this->assertSame( [ $sync, 'schedule_soon' ], $registered['update_option_inkmeter_count_drafts'] );
	}

	public function testSync_CountingDrafts_SendsTheDraftDaysToo(): void {
		$this->connection->connect( 'sfs_token', 'writer@example.com', [] );
		$this->post_dates->method( 'get_dates' )->willReturn( [ '2026-10-09', '2026-10-07' ] );
		$this->goal_settings->method( 'counts_drafts' )->willReturn( true );
		$drafts = $this->createMock( DraftDates::class );
		$drafts->method( 'get_dates' )->willReturn( [ '2026-10-08', '2026-10-07' ] );
		$sent = null;
		$this->api->method( 'request' )->willReturnCallback(
			function ( string $method, string $path, string $token, array $body ) use ( &$sent ): array {
				$sent = $body;
				return [
					'status' => 200,
					'data'   => [],
				];
			}
		);

		( new DaysSync( $this->api, $this->connection, $this->post_dates, $this->goal_settings, $drafts ) )->sync();

		$this->assertSame( [ '2026-10-09', '2026-10-08', '2026-10-07' ], $sent['days'] );
		$this->assertTrue( $sent['counts_drafts'] );
	}

	public function testScheduleSoon_NotConnected_DoesNothing(): void {
		$this->cron->expects( $this->never() )->method( 'wp_schedule_single_event' );

		$this->create_sync()->schedule_soon();
	}

	private function create_sync(): DaysSync {
		return new DaysSync( $this->api, $this->connection, $this->post_dates, $this->goal_settings );
	}
}
