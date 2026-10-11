<?php declare( strict_types=1 );

namespace MerkushinTest\Inkmeter\Pro;

use Merkushin\Inkmeter\Pro\Connection;
use Merkushin\Inkmeter\Pro\Pro;
use Merkushin\Inkmeter\Streak;
use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\TestCase;

class ProTest extends TestCase {
	use OptionsStore;

	/**
	 * @var Connection
	 */
	private $connection;

	protected function setUp(): void {
		ServiceFactory::set_custom_options( $this->create_options_store( $this ) );
		ServiceFactory::set_custom_hooks( $this->createMock( Hooks::class ) );
		$capabilities = $this->createMock( Capabilities::class );
		$capabilities->method( 'current_user_can' )->willReturn( true );
		ServiceFactory::set_custom_capabilities( $capabilities );
		$this->connection = new Connection();
	}

	protected function tearDown(): void {
		ServiceFactory::set_custom_options( null );
		ServiceFactory::set_custom_hooks( null );
		ServiceFactory::set_custom_capabilities( null );
	}

	public function testInit_Always_FiltersTheFrozenPeriods(): void {
		$hooks = $this->createMock( Hooks::class );
		ServiceFactory::set_custom_hooks( $hooks );
		$pro = new Pro( '1.1.0' );

		$filters = [];
		$hooks->method( 'add_filter' )->willReturnCallback(
			function ( string $hook, $callback ) use ( &$filters ): bool {
				$filters[ $hook ] = $callback;
				return true;
			}
		);

		$pro->init();

		$this->assertSame( [ $pro, 'frozen_periods' ], $filters[ Streak::FROZEN_FILTER ] );
		$this->assertSame( [ $pro, 'account_days' ], $filters[ Streak::ACCOUNT_DAYS_FILTER ] );
	}

	public function testFrozenPeriods_ProWithFreezes_ReturnsThemForTheUnit(): void {
		$this->connection->connect(
			'sfs_token',
			'writer@example.com',
			[
				'plan'     => 'pro',
				'features' => [ 'reminders', 'freezes' ],
			]
		);
		$this->connection->record_streak(
			[
				'unit'         => 'day',
				'frozen'       => [ '2026-10-08' ],
				'freezes_left' => 1,
			]
		);
		$pro = new Pro( '1.1.0' );

		$this->assertSame( [ '2026-10-08' ], $pro->frozen_periods( [], 'day' ) );
		$this->assertSame( [], $pro->frozen_periods( [], 'week' ) );
		$this->assertSame( 1, $pro->freezes_left() );
	}

	public function testFrozenPeriods_WithoutFreezes_LeavesThemAlone(): void {
		$this->connection->connect(
			'sfs_token',
			'writer@example.com',
			[
				'plan'     => 'free',
				'features' => [],
			]
		);
		$this->connection->record_streak( [ 'frozen' => [ '2026-10-08' ] ] );
		$pro = new Pro( '1.1.0' );

		$this->assertSame( [], $pro->frozen_periods( [], 'day' ) );
		$this->assertNull( $pro->freezes_left() );
	}

	public function testAccountDays_WithHistory_ReturnsTheAccountsDays(): void {
		$this->connection->connect(
			'sfs_token',
			'writer@example.com',
			[
				'plan'     => 'pro',
				'features' => [ 'history' ],
			]
		);
		$this->connection->record_streak( [ 'account_days' => [ '2026-10-07', 8, '2026-10-08' ] ] );

		$this->assertSame( [ '2026-10-07', '2026-10-08' ], ( new Pro( '1.1.0' ) )->account_days( null ) );
	}

	public function testAccountDays_WithoutHistory_LeavesThemAlone(): void {
		$this->connection->connect(
			'sfs_token',
			'writer@example.com',
			[
				'plan'     => 'free',
				'features' => [],
			]
		);
		$this->connection->record_streak( [ 'account_days' => [ '2026-10-08' ] ] );

		$this->assertNull( ( new Pro( '1.1.0' ) )->account_days( null ) );
	}
}
