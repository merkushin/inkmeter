<?php declare( strict_types=1 );

namespace Merkushin\Inkmeter;

defined( 'ABSPATH' ) || exit;

/**
 * Lays out the publishing history as weeks of days, for the panel's heatmap.
 *
 * Pure logic with no WordPress dependencies: dates in, weeks out.
 */
class HistoryCalendar {
	public const PUBLISHED = 'published';

	public const FROZEN = 'frozen';

	public const EMPTY = 'empty';

	public const FUTURE = 'future';

	/**
	 * @param string[] $dates        Days with a published post (Y-m-d, site timezone).
	 * @param string[] $frozen       Periods streak freezes covered: days, or the first day of weeks.
	 * @param bool     $frozen_weeks Whether $frozen holds weeks (weekly goal) rather than days.
	 * @param string   $today        Today (Y-m-d, site timezone).
	 * @param int      $week_start   First day of the week, 0 (Sunday) to 6.
	 * @param int      $weeks        How many weeks to show, ending with this one.
	 *
	 * @return array{
	 *     weeks: array<int, array{month: ?string, days: array<int, array{date: string, state: string}>}>,
	 *     published: int,
	 *     days: int
	 * } Oldest week first. A week's month is set ('Y-m') when the month starts in it, or it's the first
	 *   week; published counts the days with a post up to today, days the days shown up to today.
	 */
	public function build( array $dates, array $frozen, bool $frozen_weeks, string $today, int $week_start, int $weeks ): array {
		$published = array_fill_keys( $dates, true );
		$frozen    = array_fill_keys( $frozen, true );

		$weeks      = max( 1, $weeks );
		$offset     = ( (int) $this->date( $today )->format( 'w' ) - $week_start + 7 ) % 7;
		$this_week  = $this->add_days( $today, -$offset );
		$first_week = $this->add_days( $this_week, -7 * ( $weeks - 1 ) );

		$result = [
			'weeks'     => [],
			'published' => 0,
			'days'      => 0,
		];
		for ( $week = 0; $week < $weeks; $week++ ) {
			$start = $this->add_days( $first_week, 7 * $week );
			$month = 0 === $week ? substr( $start, 0, 7 ) : null;
			$days  = [];
			for ( $day = 0; $day < 7; $day++ ) {
				$date = $this->add_days( $start, $day );
				if ( 0 < $week && '01' === substr( $date, 8, 2 ) ) {
					$month = substr( $date, 0, 7 );
				}

				if ( $date > $today ) {
					$state = self::FUTURE;
				} elseif ( isset( $published[ $date ] ) ) {
					$state = self::PUBLISHED;
				} elseif ( isset( $frozen[ $frozen_weeks ? $start : $date ] ) ) {
					$state = self::FROZEN;
				} else {
					$state = self::EMPTY;
				}

				if ( self::FUTURE !== $state ) {
					++$result['days'];
					if ( self::PUBLISHED === $state ) {
						++$result['published'];
					}
				}
				$days[] = [
					'date'  => $date,
					'state' => $state,
				];
			}
			$result['weeks'][] = [
				'month' => $month,
				'days'  => $days,
			];
		}

		// The first week names its month even mid-month; when the next month starts a week later,
		// the two labels would overlap, so the next month's label wins.
		if ( isset( $result['weeks'][1] ) && null !== $result['weeks'][1]['month'] ) {
			$result['weeks'][0]['month'] = null;
		}

		return $result;
	}

	private function add_days( string $date, int $days ): string {
		return $this->date( $date )->modify( sprintf( '%+d day', $days ) )->format( 'Y-m-d' );
	}

	private function date( string $date ): \DateTimeImmutable {
		return new \DateTimeImmutable( $date, new \DateTimeZone( 'UTC' ) );
	}
}
