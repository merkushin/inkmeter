<?php declare( strict_types=1 );

namespace MerkushinTest\Inkmeter\Pro;

use Merkushin\Wpal\ServiceFactory;
use Merkushin\Inkmeter\Pro\Connection;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase {
	use OptionsStore;

	protected function setUp(): void {
		ServiceFactory::set_custom_options( $this->create_options_store( $this ) );
	}

	protected function tearDown(): void {
		ServiceFactory::set_custom_options( null );
	}

	public function testNew_NothingStored_IsNotConnected(): void {
		$connection = new Connection();

		$this->assertFalse( $connection->is_connected() );
		$this->assertNull( $connection->token() );
		$this->assertSame( 'free', $connection->plan() );
		$this->assertFalse( $connection->has_feature( Connection::FEATURE_REMINDERS ) );
		$this->assertNull( $connection->last_synced_at() );
	}

	public function testConnect_Pro_StoresTokenEmailAndFeatures(): void {
		$connection = new Connection();

		$connection->connect(
			'sfs_token',
			'writer@example.com',
			[
				'plan'     => 'pro',
				'features' => [ 'reminders' ],
			]
		);

		$this->assertTrue( $connection->is_connected() );
		$this->assertSame( 'sfs_token', $connection->token() );
		$this->assertSame( 'writer@example.com', $connection->email() );
		$this->assertSame( 'pro', $connection->plan() );
		$this->assertTrue( $connection->has_feature( Connection::FEATURE_REMINDERS ) );
	}

	public function testUpdateEntitlements_Downgrade_KeepsTokenAndDropsFeatures(): void {
		$connection = new Connection();
		$connection->connect(
			'sfs_token',
			'writer@example.com',
			[
				'plan'     => 'pro',
				'features' => [ 'reminders' ],
			]
		);

		$connection->update_entitlements(
			[
				'plan'     => 'free',
				'features' => [],
			]
		);

		$this->assertSame( 'sfs_token', $connection->token() );
		$this->assertSame( 'free', $connection->plan() );
		$this->assertFalse( $connection->has_feature( Connection::FEATURE_REMINDERS ) );
	}

	public function testRecordSync_Always_RemembersTheTime(): void {
		$connection = new Connection();
		$connection->connect( 'sfs_token', 'writer@example.com', [] );

		$connection->record_sync( 1791500000 );

		$this->assertSame( 1791500000, $connection->last_synced_at() );
		$this->assertSame( 'sfs_token', $connection->token() );
	}

	public function testForget_Connected_RemovesEverything(): void {
		$connection = new Connection();
		$connection->connect(
			'sfs_token',
			'writer@example.com',
			[
				'plan'     => 'pro',
				'features' => [ 'reminders' ],
			]
		);

		$connection->forget();

		$this->assertFalse( $connection->is_connected() );
		$this->assertArrayNotHasKey( Connection::OPTION, $this->stored_options );
	}

	public function testConnect_Always_StoresWithoutAutoload(): void {
		$options = $this->createMock( \Merkushin\Wpal\Service\Options::class );
		$options->method( 'get_option' )->willReturn( [] );
		$options->expects( $this->atLeastOnce() )->method( 'update_option' )->with( Connection::OPTION, $this->anything(), false );
		ServiceFactory::set_custom_options( $options );

		( new Connection() )->connect( 'sfs_token', 'writer@example.com', [] );
	}
}
