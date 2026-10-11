<?php declare( strict_types=1 );

namespace Merkushin\Inkmeter\Pro;

use Merkushin\Inkmeter\Streak;
use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Inkmeter Pro: the optional connection to the Inkmeter service.
 *
 * INKMETER_API_URL and INKMETER_SITE_URL in wp-config.php point the
 * plugin at another Inkmeter server, e.g. a local one during development.
 */
class Pro {
	public const API_URL = 'https://api.streakfire.org';

	public const SITE_URL = 'https://streakfire.org';

	/**
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * @var Hooks
	 */
	private $hooks;

	/**
	 * @var Connection
	 */
	private $connection;

	/**
	 * @var DaysSync
	 */
	private $sync;

	/**
	 * @var SettingsPage
	 */
	private $settings_page;

	public function __construct( string $plugin_version ) {
		$api_url  = defined( 'INKMETER_API_URL' ) ? (string) INKMETER_API_URL : self::API_URL;
		$site_url = defined( 'INKMETER_SITE_URL' ) ? (string) INKMETER_SITE_URL : self::SITE_URL;

		$this->capabilities  = ServiceFactory::create_capabilities();
		$this->hooks         = ServiceFactory::create_hooks();
		$this->connection    = new Connection();
		$api                 = new Api( $api_url, 'Inkmeter/' . $plugin_version );
		$this->sync          = new DaysSync( $api, $this->connection );
		$this->settings_page = new SettingsPage( $api, $this->connection, $this->sync, $site_url );
	}

	public function init(): void {
		$this->sync->init();
		$this->settings_page->init();
		$this->hooks->add_filter( Streak::FROZEN_FILTER, [ $this, 'frozen_periods' ], 10, 2 );
		$this->hooks->add_filter( Streak::ACCOUNT_DAYS_FILTER, [ $this, 'account_days' ] );
	}

	/**
	 * The panel's streak counts the periods streak freezes covered, as the service does.
	 *
	 * @param mixed  $periods
	 * @param string $unit    'day' or 'week'.
	 *
	 * @return string[]
	 */
	public function frozen_periods( $periods, $unit ): array {
		if ( ! $this->connection->has_feature( Connection::FEATURE_FREEZES ) ) {
			return is_array( $periods ) ? $periods : [];
		}

		return $this->connection->frozen( (string) $unit );
	}

	/**
	 * Adds the account's days from all its sites, which the service keeps
	 * through reinstalls and site moves, to the panel's streak and calendar.
	 *
	 * @param mixed $days
	 *
	 * @return string[]|null
	 */
	public function account_days( $days ): ?array {
		if ( ! $this->connection->has_feature( Connection::FEATURE_HISTORY ) ) {
			return is_array( $days ) ? $days : null;
		}

		return $this->connection->account_days();
	}

	/**
	 * Streak freezes left this month, or null without them.
	 */
	public function freezes_left(): ?int {
		return $this->connection->has_feature( Connection::FEATURE_FREEZES ) ? $this->connection->freezes_left() : null;
	}

	/**
	 * Where the panel points admins to set up reminders, or null when they
	 * already have them or can't manage the plugin.
	 */
	public function reminders_url(): ?string {
		if ( ! $this->capabilities->current_user_can( 'manage_options' ) || $this->connection->has_feature( Connection::FEATURE_REMINDERS ) ) {
			return null;
		}

		return $this->settings_page->page_url();
	}
}
