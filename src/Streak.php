<?php declare( strict_types=1 );

namespace Merkushin\Inkmeter;

use Merkushin\Wpal\Service\Dates;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\Service\PostTypes;
use Merkushin\Wpal\Service\Transient;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

class Streak {
	public const TRANSIENT_KEY = 'inkmeter_summary';

	/**
	 * Filters the periods streak freezes covered (days, or week starts) for a
	 * unit, 'day' or 'week'. Inkmeter Pro fills it from the Inkmeter Pro service.
	 */
	public const FROZEN_FILTER = 'inkmeter_frozen_periods';

	/**
	 * Filters the days (Y-m-d) the site's Inkmeter account published on across
	 * all its sites, or null without them. Inkmeter Pro fills it, so the streak
	 * and calendar count every connected site.
	 */
	public const ACCOUNT_DAYS_FILTER = 'inkmeter_account_days';

	private const CACHE_TTL = 86400;

	/**
	 * @var Hooks
	 */
	private $hooks;

	/**
	 * @var Transient
	 */
	private $transient;

	/**
	 * @var PostTypes
	 */
	private $post_types;

	/**
	 * @var Dates
	 */
	private $dates;

	/**
	 * @var PublishedPostDates
	 */
	private $post_dates;

	/**
	 * @var StreakCalculator
	 */
	private $calculator;

	/**
	 * @var GoalSettings
	 */
	private $goal_settings;

	public function __construct( ?PublishedPostDates $post_dates = null, ?StreakCalculator $calculator = null, ?GoalSettings $goal_settings = null ) {
		$this->hooks         = ServiceFactory::create_hooks();
		$this->transient     = ServiceFactory::create_transient();
		$this->post_types    = ServiceFactory::create_post_types();
		$this->dates         = ServiceFactory::create_dates();
		$this->post_dates    = $post_dates ?? new PublishedPostDates();
		$this->calculator    = $calculator ?? new StreakCalculator();
		$this->goal_settings = $goal_settings ?? new GoalSettings();
	}

	public function init(): void {
		$this->hooks->add_action( 'save_post', [ $this, 'clear_cache' ] );
		$this->hooks->add_action( 'delete_post', [ $this, 'clear_cache' ] );
	}

	/**
	 * @return array<string, mixed> StreakCalculator::calculate()'s summary for the site's goal.
	 */
	public function get_summary(): array {
		$today      = (string) $this->dates->current_time( 'Y-m-d' );
		$goal       = $this->goal_settings->goal();
		$week_start = $this->goal_settings->week_start();
		$frozen     = $this->hooks->apply_filters( self::FROZEN_FILTER, [], $goal->is_weekly() ? 'week' : 'day' );
		$frozen     = is_array( $frozen ) ? array_values( array_filter( $frozen, 'is_string' ) ) : [];
		$account    = $this->hooks->apply_filters( self::ACCOUNT_DAYS_FILTER, null );
		$account    = is_array( $account ) ? array_values( array_filter( $account, 'is_string' ) ) : null;

		// The cache is tied to the day, goal, week start, freezes and account days it
		// was computed for, so the streak is never stale after midnight or a change of goal.
		$key    = $today . '|' . $goal->key() . '|' . $week_start . '|' . md5( implode( ',', $frozen ) )
			. '|' . ( null === $account ? '-' : md5( implode( ',', $account ) ) );
		$cached = $this->transient->get_transient( self::TRANSIENT_KEY );
		if ( is_array( $cached ) && ( $cached['key'] ?? null ) === $key && is_array( $cached['summary'] ?? null ) ) {
			return $cached['summary'];
		}

		$dates = $this->post_dates->get_dates();
		if ( null !== $account ) {
			// This site's own days count at once; the account's arrive with the next sync.
			$dates = array_values( array_unique( array_merge( $dates, $account ) ) );
		}
		$summary = $this->calculator->calculate( $dates, $today, $goal, $week_start, $frozen );

		// For the history calendar: the past year's days (a year of weeks fits in 371) and the freezes.
		$since                   = gmdate( 'Y-m-d', (int) strtotime( $today . ' -371 days' ) );
		$summary['recent_days']  = array_values(
			array_filter(
				$dates,
				static function ( string $date ) use ( $since, $today ): bool {
					return $date >= $since && $date <= $today;
				}
			)
		);
		$summary['frozen']       = $frozen;
		$summary['across_sites'] = null !== $account;

		$this->transient->set_transient(
			self::TRANSIENT_KEY,
			[
				'key'     => $key,
				'summary' => $summary,
			],
			self::CACHE_TTL
		);

		return $summary;
	}

	public function goal(): Goal {
		return $this->goal_settings->goal();
	}

	public function week_start(): int {
		return $this->goal_settings->week_start();
	}

	public function save_goal( Goal $goal ): void {
		$this->goal_settings->save( $goal );
		$this->transient->delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * @param int|string $post_id
	 */
	public function clear_cache( $post_id ): void {
		if ( 'post' === $this->post_types->get_post_type( (int) $post_id ) ) {
			$this->transient->delete_transient( self::TRANSIENT_KEY );
		}
	}
}
