<?php declare( strict_types=1 );

namespace Merkushin\Inkmeter\Pro;

use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * The site's link to an Inkmeter account: its token, the account's email and
 * plan, and when the published days were last synced. Stored in one option
 * that isn't autoloaded, since only the settings page and the sync read it.
 */
class Connection {
	public const OPTION = 'inkmeter_connection';

	public const FEATURE_REMINDERS = 'reminders';

	public const FEATURE_FREEZES = 'freezes';

	public const FEATURE_HISTORY = 'history';

	public const FEATURE_RECAPS = 'recaps';

	/**
	 * @var Options
	 */
	private $options;

	public function __construct() {
		$this->options = ServiceFactory::create_options();
	}

	public function is_connected(): bool {
		return null !== $this->token();
	}

	public function token(): ?string {
		$token = $this->get()['token'] ?? null;

		return is_string( $token ) && '' !== $token ? $token : null;
	}

	public function email(): string {
		return (string) ( $this->get()['email'] ?? '' );
	}

	/**
	 * @return string 'free' or 'pro'.
	 */
	public function plan(): string {
		return (string) ( $this->get()['plan'] ?? 'free' );
	}

	public function has_feature( string $feature ): bool {
		$features = $this->get()['features'] ?? [];

		return is_array( $features ) && in_array( $feature, $features, true );
	}

	/**
	 * @return int|null Unix time of the last successful sync.
	 */
	public function last_synced_at(): ?int {
		$time = $this->get()['synced_at'] ?? null;

		return is_int( $time ) ? $time : null;
	}

	/**
	 * @param array<string, mixed> $entitlements As returned by the API: plan and features.
	 */
	public function connect( string $token, string $email, array $entitlements ): void {
		$this->set(
			[
				'token' => $token,
				'email' => $email,
			]
		);
		$this->update_entitlements( $entitlements );
	}

	/**
	 * @param array<string, mixed> $entitlements As returned by the API: plan and features.
	 */
	public function update_entitlements( array $entitlements ): void {
		$features = $entitlements['features'] ?? [];

		$this->set(
			[
				'plan'     => (string) ( $entitlements['plan'] ?? 'free' ),
				'features' => is_array( $features ) ? array_values( array_filter( $features, 'is_string' ) ) : [],
			]
		);
	}

	public function record_sync( int $time ): void {
		$this->set( [ 'synced_at' => $time ] );
	}

	/**
	 * Keeps the freezes and history days from a streak the API returned (/v1/days or /v1/streak).
	 *
	 * @param array<string, mixed> $streak
	 */
	public function record_streak( array $streak ): void {
		$frozen  = $streak['frozen'] ?? [];
		$history = $streak['history_days'] ?? [];

		$this->set(
			[
				'frozen'       => is_array( $frozen ) ? array_values( array_filter( $frozen, 'is_string' ) ) : [],
				'history_days' => is_array( $history ) ? array_values( array_filter( $history, 'is_string' ) ) : [],
				'frozen_unit'  => (string) ( $streak['unit'] ?? 'day' ),
				'freezes_left' => (int) ( $streak['freezes_left'] ?? 0 ),
			]
		);
	}

	/**
	 * Periods streak freezes covered, for 'day' or 'week'.
	 *
	 * @return string[]
	 */
	public function frozen( string $unit ): array {
		$data = $this->get();
		if ( ( $data['frozen_unit'] ?? null ) !== $unit || ! is_array( $data['frozen'] ?? null ) ) {
			return [];
		}

		return $data['frozen'];
	}

	/**
	 * The past year's days with a post on any of the account's sites, as of the last sync.
	 *
	 * @return string[]
	 */
	public function history_days(): array {
		$days = $this->get()['history_days'] ?? [];

		return is_array( $days ) ? $days : [];
	}

	public function freezes_left(): int {
		return (int) ( $this->get()['freezes_left'] ?? 0 );
	}

	public function forget(): void {
		$this->options->delete_option( self::OPTION );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get(): array {
		$value = $this->options->get_option( self::OPTION, [] );

		return is_array( $value ) ? $value : [];
	}

	/**
	 * @param array<string, mixed> $changes Merged into the stored value.
	 */
	private function set( array $changes ): void {
		$this->options->update_option( self::OPTION, array_merge( $this->get(), $changes ), false );
	}
}
