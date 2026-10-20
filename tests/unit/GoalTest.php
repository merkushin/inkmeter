<?php declare( strict_types=1 );

namespace MerkushinTest\Inkmeter;

use Merkushin\Inkmeter\Goal;
use PHPUnit\Framework\TestCase;

class GoalTest extends TestCase {
	public function testDaily_Always_AsksForOneDay(): void {
		$goal = Goal::daily();

		$this->assertFalse( $goal->is_weekly() );
		$this->assertSame( 1, $goal->days() );
		$this->assertSame( 'daily', $goal->key() );
	}

	public function testWeekly_OutOfRange_IsClamped(): void {
		$this->assertSame( 1, Goal::weekly( 0 )->days() );
		$this->assertSame( 6, Goal::weekly( 7 )->days() );
		$this->assertSame( 'weekly-3', Goal::weekly( 3 )->key() );
	}

	public function testFromArray_StoredWeekly_RoundTrips(): void {
		$goal = Goal::from_array( Goal::weekly( 4 )->to_array() );

		$this->assertTrue( $goal->is_weekly() );
		$this->assertSame( 4, $goal->days() );
	}

	/**
	 * @dataProvider provide_invalid_stored_values
	 *
	 * @param mixed $value
	 */
	public function testFromArray_NothingOrInvalidStored_IsDaily( $value ): void {
		$this->assertSame( 'daily', Goal::from_array( $value )->key() );
	}

	public function provide_invalid_stored_values(): array {
		return [
			'missing option' => [ false ],
			'string'         => [ 'weekly' ],
			'unknown type'   => [
				[
					'type' => 'monthly',
					'days' => 2,
				],
			],
			'days as string' => [
				[
					'type' => 'weekly',
					'days' => '3',
				],
			],
		];
	}

	public function testFromKey_ValidAndInvalidKeys(): void {
		$this->assertSame( 'daily', Goal::from_key( 'daily' )->key() );
		$this->assertSame( 'weekly-6', Goal::from_key( 'weekly-6' )->key() );
		foreach ( [ '', 'weekly-0', 'weekly-7', 'weekly-3x', 'monthly' ] as $key ) {
			$this->assertNull( Goal::from_key( $key ), $key );
		}
	}

	public function testAll_Always_ListsDailyThenOneToSixDaysAWeek(): void {
		$keys = array_map(
			static function ( Goal $goal ): string {
				return $goal->key();
			},
			Goal::all()
		);

		$this->assertSame( [ 'daily', 'weekly-1', 'weekly-2', 'weekly-3', 'weekly-4', 'weekly-5', 'weekly-6' ], $keys );
	}
}
