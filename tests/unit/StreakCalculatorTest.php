<?php declare( strict_types=1 );

namespace MerkushinTest\Inkmeter;

use Merkushin\Inkmeter\Goal;
use Merkushin\Inkmeter\StreakCalculator;
use PHPUnit\Framework\TestCase;

class StreakCalculatorTest extends TestCase {
	private const TODAY = '2026-03-02';

	/**
	 * @dataProvider provide_streaks
	 *
	 * @param string[] $dates
	 */
	public function testCalculate_GivenDates_ReturnsStreak( array $dates, int $expected_streak, bool $expected_active ): void {
		$summary = ( new StreakCalculator() )->calculate( $dates, self::TODAY );

		$this->assertSame( $expected_streak, $summary['streak'] );
		$this->assertSame( $expected_active, $summary['is_active_today'] );
	}

	public function provide_streaks(): array {
		return [
			'no posts'                                 => [ [], 0, false ],
			'only today'                               => [ [ '2026-03-02' ], 1, true ],
			'only yesterday keeps the streak alive'    => [ [ '2026-03-01' ], 1, false ],
			'two days ago breaks the streak'           => [ [ '2026-02-28' ], 0, false ],
			'consecutive days across a month boundary' => [ [ '2026-03-02', '2026-03-01', '2026-02-28', '2026-02-27' ], 4, true ],
			'gap stops the count'                      => [ [ '2026-03-02', '2026-03-01', '2026-02-27' ], 2, true ],
			'consecutive up to yesterday'              => [ [ '2026-03-01', '2026-02-28', '2026-02-27' ], 3, false ],
			'duplicate days count once and do not break the streak' => [ [ '2026-03-02', '2026-03-02', '2026-03-01', '2026-03-01', '2026-02-28' ], 3, true ],
			'unsorted input'                           => [ [ '2026-02-28', '2026-03-02', '2026-03-01' ], 3, true ],
			'future dates are ignored'                 => [ [ '2026-03-05', '2026-03-01' ], 1, false ],
		];
	}

	public function testCalculate_AcrossLeapDay_CountsEveryDay(): void {
		$summary = ( new StreakCalculator() )->calculate( [ '2024-03-01', '2024-02-29', '2024-02-28' ], '2024-03-01' );

		$this->assertSame( 3, $summary['streak'] );
	}

	public function testCalculate_GivenPosts_ReturnsLatestDate(): void {
		$summary = ( new StreakCalculator() )->calculate( [ '2025-12-01', '2026-01-15', '2025-06-30' ], self::TODAY );

		$this->assertSame( '2026-01-15', $summary['last_post_date'] );
	}

	public function testCalculate_NoPosts_ReturnsNullLastPostDate(): void {
		$summary = ( new StreakCalculator() )->calculate( [], self::TODAY );

		$this->assertNull( $summary['last_post_date'] );
	}

	/**
	 * @dataProvider provide_milestones
	 */
	public function testCalculate_GivenStreak_ReturnsNextMilestone( int $streak, int $expected ): void {
		$dates = [];
		$day   = new \DateTimeImmutable( self::TODAY );
		for ( $i = 0; $i < $streak; $i++ ) {
			$dates[] = $day->modify( "-{$i} days" )->format( 'Y-m-d' );
		}

		$summary = ( new StreakCalculator() )->calculate( $dates, self::TODAY );

		$this->assertSame( $streak, $summary['streak'] );
		$this->assertSame( $expected, $summary['next_milestone'] );
	}

	public function provide_milestones(): array {
		return [
			[ 0, 3 ],
			[ 2, 3 ],
			[ 3, 7 ],
			[ 29, 30 ],
			[ 99, 100 ],
			[ 100, 125 ],
			[ 124, 125 ],
			[ 125, 150 ],
		];
	}

	/**
	 * Thursday, in the week of Monday March 2 to Sunday March 8.
	 */
	private const THURSDAY = '2026-03-05';

	private const MONDAY = 1;

	private const SUNDAY = 0;

	/**
	 * @dataProvider provide_weekly_streaks
	 *
	 * @param string[] $dates
	 */
	public function testCalculate_WeeklyGoal_CountsWeeksThatMetIt( array $dates, int $expected_streak, bool $expected_met, int $expected_this_week ): void {
		$summary = ( new StreakCalculator() )->calculate( $dates, self::THURSDAY, Goal::weekly( 3 ), self::MONDAY );

		$this->assertSame( 'week', $summary['unit'] );
		$this->assertSame( 3, $summary['goal_days'] );
		$this->assertSame( $expected_streak, $summary['streak'] );
		$this->assertSame( $expected_met, $summary['is_goal_met'] );
		$this->assertSame( $expected_this_week, $summary['period_days'] );
	}

	public function provide_weekly_streaks(): array {
		return [
			'no posts'                                 => [ [], 0, false, 0 ],
			'goal met this week'                       => [ [ '2026-03-02', '2026-03-03', '2026-03-04' ], 1, true, 3 ],
			'this week in progress keeps last week'    => [ [ '2026-03-03', '2026-02-23', '2026-02-25', '2026-02-27' ], 1, false, 1 ],
			'three weeks in a row'                     => [ [ '2026-03-02', '2026-03-03', '2026-03-05', '2026-02-23', '2026-02-24', '2026-03-01', '2026-02-16', '2026-02-18', '2026-02-20' ], 3, true, 3 ],
			'a short week breaks the streak'           => [ [ '2026-02-23', '2026-02-25', '2026-02-16', '2026-02-18', '2026-02-20' ], 0, false, 0 ],
			'more days than the goal still count once' => [ [ '2026-02-23', '2026-02-24', '2026-02-25', '2026-02-26', '2026-02-27' ], 1, false, 0 ],
			'several posts on one day are one day'     => [ [ '2026-03-02', '2026-03-02', '2026-03-02' ], 0, false, 1 ],
			'future days this week are ignored'        => [ [ '2026-03-02', '2026-03-03', '2026-03-07' ], 0, false, 2 ],
			'a week without posts between breaks it'   => [ [ '2026-02-16', '2026-02-17', '2026-02-18', '2026-03-02', '2026-03-03', '2026-03-04' ], 1, true, 3 ],
		];
	}

	public function testCalculate_WeekStartsOnSunday_GroupsSundayWithTheFollowingDays(): void {
		$dates = [ '2026-03-01', '2026-03-03', '2026-03-04' ];

		$sunday = ( new StreakCalculator() )->calculate( $dates, self::THURSDAY, Goal::weekly( 3 ), self::SUNDAY );
		$monday = ( new StreakCalculator() )->calculate( $dates, self::THURSDAY, Goal::weekly( 3 ), self::MONDAY );

		$this->assertSame( [ 1, true, 3 ], [ $sunday['streak'], $sunday['is_goal_met'], $sunday['period_days'] ] );
		$this->assertSame( [ 0, false, 2 ], [ $monday['streak'], $monday['is_goal_met'], $monday['period_days'] ] );
	}

	/**
	 * @dataProvider provide_days_left
	 */
	public function testCalculate_WeeklyGoal_CountsDaysLeftIncludingToday( string $today, int $week_start, int $expected ): void {
		$summary = ( new StreakCalculator() )->calculate( [], $today, Goal::weekly( 2 ), $week_start );

		$this->assertSame( $expected, $summary['days_left'] );
	}

	public function provide_days_left(): array {
		return [
			'Monday, weeks start Monday'   => [ '2026-03-02', self::MONDAY, 7 ],
			'Thursday, weeks start Monday' => [ self::THURSDAY, self::MONDAY, 4 ],
			'Sunday, weeks start Monday'   => [ '2026-03-08', self::MONDAY, 1 ],
			'Sunday, weeks start Sunday'   => [ '2026-03-08', self::SUNDAY, 7 ],
			'Saturday, weeks start Sunday' => [ '2026-03-07', self::SUNDAY, 1 ],
		];
	}

	public function testCalculate_WeeklyGoal_AcrossTheNewYear(): void {
		// Monday 2025-12-29 to Sunday 2026-01-04 is one week.
		$summary = ( new StreakCalculator() )->calculate( [ '2025-12-29', '2025-12-31', '2026-01-02' ], '2026-01-03', Goal::weekly( 3 ), self::MONDAY );

		$this->assertSame( [ 1, true ], [ $summary['streak'], $summary['is_goal_met'] ] );
	}

	/**
	 * @dataProvider provide_week_milestones
	 */
	public function testCalculate_WeeklyGoal_UsesWeekMilestones( int $weeks, int $expected ): void {
		$dates = [];
		for ( $week = 0; $week < $weeks; $week++ ) {
			$dates[] = ( new \DateTimeImmutable( '2026-03-02' ) )->modify( '-' . ( 7 * $week ) . ' days' )->format( 'Y-m-d' );
		}

		$summary = ( new StreakCalculator() )->calculate( $dates, self::THURSDAY, Goal::weekly( 1 ), self::MONDAY );

		$this->assertSame( $weeks, $summary['streak'] );
		$this->assertSame( $expected, $summary['next_milestone'] );
	}

	public function provide_week_milestones(): array {
		return [
			'none yet'     => [ 0, 2 ],
			'two weeks'    => [ 2, 4 ],
			'eleven weeks' => [ 11, 12 ],
			'a year'       => [ 52, 78 ],
			'past a year'  => [ 60, 78 ],
		];
	}

	public function testCalculate_DailyGoal_DescribesTodayAsThePeriod(): void {
		$summary = ( new StreakCalculator() )->calculate( [ '2026-03-02' ], self::TODAY, Goal::daily() );

		$this->assertSame( 'day', $summary['unit'] );
		$this->assertSame( [ true, 1, 1, 1 ], [ $summary['is_goal_met'], $summary['goal_days'], $summary['period_days'], $summary['days_left'] ] );
	}

	/**
	 * The same cases as the server's TestFreezesKeepTheStreakWithoutCounting.
	 *
	 * @dataProvider provide_daily_freezes
	 *
	 * @param string[] $dates
	 * @param string[] $frozen
	 */
	public function testCalculate_FrozenDays_KeepTheStreakWithoutCounting( array $dates, array $frozen, int $expected, bool $expected_saved ): void {
		$summary = ( new StreakCalculator() )->calculate( $dates, '2026-10-10', Goal::daily(), self::MONDAY, $frozen );

		$this->assertSame( $expected, $summary['streak'] );
		$this->assertSame( $expected_saved, $summary['saved_by_freeze'] );
	}

	public function provide_daily_freezes(): array {
		return [
			'a frozen day bridges a gap'              => [ [ '2026-10-06', '2026-10-07', '2026-10-09', '2026-10-10' ], [ '2026-10-08' ], 4, false ],
			'without the freeze the gap breaks it'    => [ [ '2026-10-06', '2026-10-07', '2026-10-09', '2026-10-10' ], [], 2, false ],
			'a frozen yesterday keeps it alive today' => [ [ '2026-10-07', '2026-10-08' ], [ '2026-10-09' ], 2, true ],
			'freezes alone are not a streak'          => [ [], [ '2026-10-08', '2026-10-09' ], 0, false ],
			'two frozen days in a row'                => [ [ '2026-10-06', '2026-10-09' ], [ '2026-10-07', '2026-10-08' ], 2, false ],
		];
	}

	public function testCalculate_FrozenWeek_KeepsTheWeeklyStreak(): void {
		$dates = [ '2026-02-16', '2026-02-18', '2026-02-20', '2026-03-02', '2026-03-03', '2026-03-04' ];

		$frozen  = ( new StreakCalculator() )->calculate( $dates, self::THURSDAY, Goal::weekly( 3 ), self::MONDAY, [ '2026-02-23' ] );
		$without = ( new StreakCalculator() )->calculate( $dates, self::THURSDAY, Goal::weekly( 3 ), self::MONDAY );

		$this->assertSame( [ 2, true, true ], [ $frozen['streak'], $frozen['is_goal_met'], $frozen['saved_by_freeze'] ] );
		$this->assertSame( [ 1, false ], [ $without['streak'], $without['saved_by_freeze'] ] );
	}
}
