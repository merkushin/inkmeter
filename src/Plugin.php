<?php declare( strict_types=1 );

namespace Merkushin\Inkmeter;

use Merkushin\Wpal\Service\Assets;
use Merkushin\Wpal\Service\Dates;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\Service\Localization;
use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\Service\Screen;
use Merkushin\Wpal\Service\UserSettings;
use Merkushin\Wpal\ServiceFactory;
use Merkushin\Inkmeter\Pro\Pro;

defined( 'ABSPATH' ) || exit;

class Plugin {
	public const VERSION = '1.1.0';

	/**
	 * The Posts list screen, where the panel is shown.
	 */
	private const SCREEN_ID = 'edit-post';

	/**
	 * Per-user setting for the panel's Screen Options checkbox: 'on' or 'off'.
	 * Stored by WordPress' user settings; the admin script updates it with setUserSetting().
	 */
	public const PANEL_SETTING = 'inkmeter_panel';

	/**
	 * User setting: 'off' once the user dismissed the panel's Inkmeter Pro link.
	 */
	public const PRO_LINK_SETTING = 'inkmeter_pro_link';

	/**
	 * Weeks in the history calendar: the past year.
	 */
	private const HISTORY_WEEKS = 53;

	/**
	 * Main plugin file path.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * @var Hooks
	 */
	private $hooks;

	/**
	 * @var Assets
	 */
	private $assets;

	/**
	 * @var Plugins
	 */
	private $plugins;

	/**
	 * @var Screen
	 */
	private $screen;

	/**
	 * @var Localization
	 */
	private $localization;

	/**
	 * @var Dates
	 */
	private $dates;

	/**
	 * @var Options
	 */
	private $options;

	/**
	 * @var UserSettings
	 */
	private $user_settings;

	/**
	 * @var Streak
	 */
	private $streak;

	/**
	 * @var GoalForm
	 */
	private $goal_form;

	/**
	 * @var Pro
	 */
	private $pro;

	/**
	 * @var Editor
	 */
	private $editor;

	public function __construct( string $plugin_file, ?Streak $streak = null, ?GoalForm $goal_form = null, ?Pro $pro = null, ?Editor $editor = null ) {
		$this->plugin_file   = $plugin_file;
		$this->hooks         = ServiceFactory::create_hooks();
		$this->assets        = ServiceFactory::create_assets();
		$this->plugins       = ServiceFactory::create_plugins();
		$this->screen        = ServiceFactory::create_screen();
		$this->localization  = ServiceFactory::create_localization();
		$this->dates         = ServiceFactory::create_dates();
		$this->options       = ServiceFactory::create_options();
		$this->user_settings = ServiceFactory::create_user_settings();
		$this->streak        = $streak ?? new Streak();
		$this->goal_form     = $goal_form ?? new GoalForm( $this->streak );
		$this->pro           = $pro ?? new Pro( self::VERSION );
		$this->editor        = $editor ?? new Editor( $this->streak, $plugin_file, self::VERSION );
	}

	public function init(): void {
		// Translations come from translate.wordpress.org language packs, which WordPress loads on demand.
		$this->hooks->add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		$this->hooks->add_action( 'all_admin_notices', [ $this, 'render_streak_panel' ] );
		$this->hooks->add_filter( 'screen_settings', [ $this, 'add_screen_option' ], 10, 2 );

		$this->streak->init();
		$this->goal_form->init();
		$this->pro->init();
		$this->editor->init();
	}

	public function enqueue_admin_assets(): void {
		if ( ! $this->is_posts_screen() ) {
			return;
		}

		$url = $this->plugins->plugin_dir_url( $this->plugin_file );

		$this->assets->wp_enqueue_style( 'inkmeter-admin', $url . 'assets/dist/styles/admin.css', [], self::VERSION );
		// The script saves the Screen Options checkbox with setUserSetting() from WordPress' utils script.
		$this->assets->wp_enqueue_script( 'inkmeter-admin', $url . 'assets/dist/javascript/admin.js', [ 'utils' ], self::VERSION, true );
	}

	public function render_streak_panel(): void {
		if ( ! $this->is_posts_screen() ) {
			return;
		}

		$summary        = $this->streak->get_summary();
		$streak         = (int) $summary['streak'];
		$is_weekly      = 'week' === ( $summary['unit'] ?? 'day' );
		$is_goal_met    = (bool) ( $summary['is_goal_met'] ?? $summary['is_active_today'] );
		$next_milestone = (int) $summary['next_milestone'];
		$progress       = min( 100, (int) round( ( $streak / $next_milestone ) * 100 ) );
		$goal_days      = (int) ( $summary['goal_days'] ?? 1 );
		$period_days    = (int) ( $summary['period_days'] ?? 0 );
		$days_needed    = max( 0, $goal_days - $period_days );
		$days_left      = (int) ( $summary['days_left'] ?? 1 );

		// The view holds all translatable text; it receives raw values plus their localized formatting.
		$accent_class         = $is_goal_met ? 'is-hot' : 'is-warm';
		$is_out_of_reach      = $is_weekly && $days_needed > $days_left;
		$status_key           = $this->get_status_key( $streak, $is_goal_met, $is_out_of_reach );
		$streak_label         = $this->format_number( $streak );
		$next_milestone_label = $this->format_number( $next_milestone );
		$progress_label       = $this->format_number( $progress );
		$last_post_label      = $this->format_date( $summary['last_post_date'] );
		$goal_days_label      = $this->format_number( $goal_days );
		$period_days_label    = $this->format_number( $period_days );
		$days_needed_label    = $this->format_number( $days_needed );
		$is_panel_visible     = $this->is_panel_visible();
		$reminders_url        = 'off' === $this->user_settings->get_user_setting( self::PRO_LINK_SETTING, 'on' ) ? null : $this->pro->reminders_url();
		$freezes_left         = $this->pro->freezes_left();
		$freezes_left_label   = null === $freezes_left ? '' : $this->format_number( $freezes_left );
		$saved_by_freeze      = (bool) ( $summary['saved_by_freeze'] ?? false );
		// With Inkmeter Pro, the account's days from all its sites join this site's own.
		$account_days       = $this->pro->history_days();
		$history_all_sites  = null !== $account_days;
		$history            = ( new HistoryCalendar() )->build(
			array_merge( (array) ( $summary['recent_days'] ?? [] ), $account_days ?? [] ),
			(array) ( $summary['frozen'] ?? [] ),
			$is_weekly,
			(string) $this->dates->current_time( 'Y-m-d' ),
			$this->streak->week_start(),
			self::HISTORY_WEEKS
		);
		$history_published  = $this->format_number( $history['published'] );
		$history_days       = $this->format_number( $history['days'] );
		$history_has_frozen = [] !== array_filter(
			$history['weeks'],
			static function ( array $week ): bool {
				return in_array( HistoryCalendar::FROZEN, array_column( $week['days'], 'state' ), true );
			}
		);
		$goal               = $this->streak->goal();
		$goal_options       = Goal::all();
		$can_change_goal    = $this->goal_form->can_change();
		$goal_action_url    = $this->goal_form->action_url();

		include __DIR__ . '/views/streak_panel.php';
	}

	/**
	 * Adds a checkbox to the Posts screen's Screen Options so each user can hide the panel.
	 *
	 * @param string $settings Screen settings HTML.
	 * @param mixed  $screen   WP_Screen object.
	 */
	public function add_screen_option( $settings, $screen ): string {
		$settings = (string) $settings;

		if ( ! is_object( $screen ) || ! isset( $screen->id ) || self::SCREEN_ID !== $screen->id ) {
			return $settings;
		}

		$is_panel_visible = $this->is_panel_visible();

		ob_start();
		include __DIR__ . '/views/screen_option.php';

		return $settings . (string) ob_get_clean();
	}

	private function is_panel_visible(): bool {
		return 'off' !== $this->user_settings->get_user_setting( self::PANEL_SETTING, 'on' );
	}

	private function is_posts_screen(): bool {
		$screen = $this->screen->get_current_screen();

		return is_object( $screen ) && isset( $screen->id ) && self::SCREEN_ID === $screen->id;
	}

	/**
	 * Prints the history calendar's grid: a column per week, a cell per day.
	 *
	 * @param array{weeks: array<int, array{month: ?string, days: array<int, array{date: string, state: string}>}>} $history
	 */
	public function render_history( array $history ): void {
		$utc = new \DateTimeZone( 'UTC' );

		echo '<div class="inkmeter-history__months" aria-hidden="true">';
		foreach ( $history['weeks'] as $week ) {
			$month = null === $week['month'] ? '' : (string) $this->dates->wp_date( 'M', (int) strtotime( $week['month'] . '-01 UTC' ), $utc );
			echo '<span>' . esc_html( $month ) . '</span>';
		}
		echo '</div><div class="inkmeter-history__weeks" aria-hidden="true">';
		foreach ( $history['weeks'] as $week ) {
			echo '<div class="inkmeter-history__week">';
			foreach ( $week['days'] as $day ) {
				printf(
					'<span class="inkmeter-history__day is-%1$s" title="%2$s"></span>',
					esc_attr( $day['state'] ),
					esc_attr( $this->history_title( $day['date'], $day['state'] ) )
				);
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Prints the goal choices for the panel's goal form.
	 */
	public function render_goal_options( Goal $selected ): void {
		foreach ( Goal::all() as $goal ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $goal->key() ),
				selected( $selected->key(), $goal->key(), false ),
				esc_html( $this->goal_label( $goal ) )
			);
		}
	}

	/**
	 * The goal in words, e.g. "Publish on 3 days a week".
	 */
	public function goal_label( Goal $goal ): string {
		if ( ! $goal->is_weekly() ) {
			return __( 'Publish every day', 'inkmeter' );
		}

		/* translators: %s: Number of days. */
		return sprintf( _n( 'Publish on %s day a week', 'Publish on %s days a week', $goal->days(), 'inkmeter' ), $this->format_number( $goal->days() ) );
	}

	/**
	 * @param bool $is_out_of_reach Whether this week can no longer meet a weekly goal.
	 *
	 * @return string One of 'start', 'on_fire', 'alive' or 'out_of_reach'; the view maps it to a message.
	 */
	private function get_status_key( int $streak, bool $is_goal_met, bool $is_out_of_reach ): string {
		if ( $is_goal_met ) {
			return 'on_fire';
		}
		if ( $is_out_of_reach ) {
			return 'out_of_reach';
		}

		return 0 === $streak ? 'start' : 'alive';
	}

	private function format_number( int $number ): string {
		return (string) $this->localization->number_format_i18n( $number );
	}

	/**
	 * A day's tooltip in the history calendar, e.g. "March 3, 2026: published".
	 */
	private function history_title( string $date, string $state ): string {
		$label = (string) $this->format_date( $date );
		switch ( $state ) {
			case HistoryCalendar::PUBLISHED:
				/* translators: %s: A date. */
				return sprintf( __( '%s: published', 'inkmeter' ), $label );
			case HistoryCalendar::FROZEN:
				/* translators: %s: A date. */
				return sprintf( __( '%s: covered by a streak freeze', 'inkmeter' ), $label );
			case HistoryCalendar::FUTURE:
				return $label;
			default:
				/* translators: %s: A date. */
				return sprintf( __( '%s: no post', 'inkmeter' ), $label );
		}
	}

	/**
	 * Formats a day (Y-m-d) with the site's date format and locale.
	 */
	private function format_date( ?string $date ): ?string {
		$utc    = new \DateTimeZone( 'UTC' );
		$parsed = null === $date ? false : \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $utc );

		if ( ! $parsed ) {
			return null;
		}

		// The day is already in the site timezone, so format it as UTC midnight to keep it unchanged.
		$formatted = $this->dates->wp_date( (string) $this->options->get_option( 'date_format', 'F j, Y' ), $parsed->getTimestamp(), $utc );

		return is_string( $formatted ) ? $formatted : null;
	}
}
