<?php declare( strict_types=1 );

namespace MerkushinTest\Inkmeter;

use Merkushin\Inkmeter\Goal;
use Merkushin\Inkmeter\GoalSettings;
use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GoalSettingsTest extends TestCase {
	/**
	 * @var Options&MockObject
	 */
	private $options;

	protected function setUp(): void {
		$this->options = $this->createMock( Options::class );
		ServiceFactory::set_custom_options( $this->options );
	}

	protected function tearDown(): void {
		ServiceFactory::set_custom_options( null );
	}

	public function testGoal_NothingSaved_IsDaily(): void {
		$this->options->method( 'get_option' )->with( GoalSettings::OPTION, null )->willReturn( null );

		$this->assertSame( 'daily', ( new GoalSettings() )->goal()->key() );
	}

	public function testGoal_WeeklySaved_ReadsIt(): void {
		$this->options->method( 'get_option' )->willReturn(
			[
				'type' => 'weekly',
				'days' => 3,
			]
		);

		$this->assertSame( 'weekly-3', ( new GoalSettings() )->goal()->key() );
	}

	public function testSave_Always_StoresAutoloaded(): void {
		$this->options->expects( $this->once() )->method( 'update_option' )->with(
			GoalSettings::OPTION,
			[
				'type' => 'weekly',
				'days' => 4,
			],
			true
		);

		( new GoalSettings() )->save( Goal::weekly( 4 ) );
	}

	public function testCountsDrafts_NothingSaved_IsOff(): void {
		$this->options->method( 'get_option' )->with( GoalSettings::DRAFTS_OPTION, '0' )->willReturn( '0' );

		$this->assertFalse( ( new GoalSettings() )->counts_drafts() );
	}

	public function testSaveCountsDrafts_On_StoresOneAutoloaded(): void {
		$this->options->expects( $this->once() )->method( 'update_option' )->with( GoalSettings::DRAFTS_OPTION, '1', true );

		( new GoalSettings() )->save_counts_drafts( true );
	}

	/**
	 * @dataProvider provide_week_starts
	 *
	 * @param mixed $stored
	 */
	public function testWeekStart_StoredValue_IsADayOfTheWeek( $stored, int $expected ): void {
		$this->options->method( 'get_option' )->with( 'start_of_week', 1 )->willReturn( $stored );

		$this->assertSame( $expected, ( new GoalSettings() )->week_start() );
	}

	public function provide_week_starts(): array {
		return [
			'Monday as a string' => [ '1', 1 ],
			'Sunday'             => [ 0, 0 ],
			'Saturday'           => [ '6', 6 ],
			'out of range'       => [ 9, 2 ],
			'not a number'       => [ 'monday', 1 ],
		];
	}
}
