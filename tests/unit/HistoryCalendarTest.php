<?php declare( strict_types=1 );

namespace MerkushinTest\Inkmeter;

use Merkushin\Inkmeter\HistoryCalendar;
use PHPUnit\Framework\TestCase;

class HistoryCalendarTest extends TestCase {
	/**
	 * Thursday, in the week of Monday March 2 to Sunday March 8.
	 */
	private const TODAY = '2026-03-05';

	public function testBuild_ThirteenWeeks_EndsWithThisWeekStartingOnTheWeekStart(): void {
		$calendar = ( new HistoryCalendar() )->build( [], [], false, self::TODAY, 1, 13 );

		$this->assertCount( 13, $calendar['weeks'] );
		$this->assertSame( '2025-12-08', $calendar['weeks'][0]['days'][0]['date'], 'the first week starts on a Monday, 12 weeks earlier' );
		$last = $calendar['weeks'][12]['days'];
		$this->assertSame( [ '2026-03-02', '2026-03-08' ], [ $last[0]['date'], $last[6]['date'] ] );
		$this->assertSame( 12 * 7 + 4, $calendar['days'], 'days up to today' );
	}

	public function testBuild_WeekStartsOnSunday_ShiftsTheColumns(): void {
		$calendar = ( new HistoryCalendar() )->build( [], [], false, self::TODAY, 0, 1 );

		$this->assertSame( '2026-03-01', $calendar['weeks'][0]['days'][0]['date'] );
	}

	public function testBuild_Days_AreMarkedPublishedFrozenEmptyOrFuture(): void {
		$calendar = ( new HistoryCalendar() )->build( [ '2026-03-02', '2026-03-04', '2026-02-01' ], [ '2026-03-03' ], false, self::TODAY, 1, 1 );

		$states = array_column( $calendar['weeks'][0]['days'], 'state', 'date' );
		$this->assertSame(
			[
				'2026-03-02' => 'published',
				'2026-03-03' => 'frozen',
				'2026-03-04' => 'published',
				'2026-03-05' => 'empty',
				'2026-03-06' => 'future',
				'2026-03-07' => 'future',
				'2026-03-08' => 'future',
			],
			$states
		);
		$this->assertSame( [ 2, 4 ], [ $calendar['published'], $calendar['days'] ], 'only the days shown count' );
	}

	public function testBuild_FrozenWeek_MarksItsDaysWithoutAPost(): void {
		// Weekly goal: the week of Feb 23 was frozen.
		$calendar = ( new HistoryCalendar() )->build( [ '2026-02-24' ], [ '2026-02-23' ], true, self::TODAY, 1, 2 );

		$states = array_count_values( array_column( $calendar['weeks'][0]['days'], 'state' ) );
		$this->assertSame(
			[
				'frozen'    => 6,
				'published' => 1,
			],
			$states
		);
	}

	public function testBuild_Months_AreLabelledWhereTheyStart(): void {
		$calendar = ( new HistoryCalendar() )->build( [], [], false, self::TODAY, 1, 13 );

		$months = array_filter( array_column( $calendar['weeks'], 'month' ) );
		$this->assertSame(
			[
				0  => '2025-12',
				3  => '2026-01',
				7  => '2026-02',
				11 => '2026-03',
			],
			$months
		);
	}
}
