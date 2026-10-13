<?php declare( strict_types=1 );

namespace Merkushin\Inkmeter;

defined( 'ABSPATH' ) || exit;

/**
 * The writing goal: publish every day, or on a number of days each week.
 */
final class Goal {
	public const DAILY = 'daily';

	public const WEEKLY = 'weekly';

	/**
	 * A weekly goal of 7 days is the daily goal, so weekly goals go up to 6.
	 */
	public const MAX_DAYS_PER_WEEK = 6;

	/**
	 * @var string
	 */
	private $type;

	/**
	 * @var int
	 */
	private $days;

	private function __construct( string $type, int $days ) {
		$this->type = $type;
		$this->days = $days;
	}

	public static function daily(): self {
		return new self( self::DAILY, 1 );
	}

	public static function weekly( int $days ): self {
		return new self( self::WEEKLY, max( 1, min( self::MAX_DAYS_PER_WEEK, $days ) ) );
	}

	/**
	 * Reads a goal stored with to_array(); anything unrecognized is the daily goal.
	 *
	 * @param mixed $value
	 */
	public static function from_array( $value ): self {
		if ( is_array( $value ) && self::WEEKLY === ( $value['type'] ?? null ) && is_int( $value['days'] ?? null ) ) {
			return self::weekly( $value['days'] );
		}

		return self::daily();
	}

	/**
	 * Reads a goal from its key(), e.g. a submitted form value; null if invalid.
	 */
	public static function from_key( string $key ): ?self {
		if ( self::DAILY === $key ) {
			return self::daily();
		}
		if ( 1 === preg_match( '/^weekly-([1-6])$/', $key, $matches ) ) {
			return self::weekly( (int) $matches[1] );
		}

		return null;
	}

	/**
	 * Every goal a writer can choose, daily first.
	 *
	 * @return self[]
	 */
	public static function all(): array {
		$goals = [ self::daily() ];
		for ( $days = 1; $days <= self::MAX_DAYS_PER_WEEK; $days++ ) {
			$goals[] = self::weekly( $days );
		}

		return $goals;
	}

	public function is_weekly(): bool {
		return self::WEEKLY === $this->type;
	}

	/**
	 * Days with a post the goal asks for in each period: 1 a day, or N a week.
	 */
	public function days(): int {
		return $this->days;
	}

	/**
	 * A short identifier: 'daily' or 'weekly-3'.
	 */
	public function key(): string {
		return $this->is_weekly() ? self::WEEKLY . '-' . $this->days : self::DAILY;
	}

	/**
	 * @return array{type: string, days: int}
	 */
	public function to_array(): array {
		return [
			'type' => $this->type,
			'days' => $this->days,
		];
	}
}
