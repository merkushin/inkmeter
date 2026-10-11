<?php declare( strict_types=1 );

namespace Merkushin\Inkmeter\Pro;

use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\Service\Dates;
use Merkushin\Wpal\Service\Errors;
use Merkushin\Wpal\Service\Formatting;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\Service\Nonces;
use Merkushin\Wpal\Service\Passwords;
use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\Service\Redirects;
use Merkushin\Wpal\Service\Sanitization;
use Merkushin\Wpal\Service\Transient;
use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\Service\Users;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Settings → Inkmeter: connects the site to Inkmeter Pro and manages it.
 *
 * Connecting: the admin is sent to streakfire.org/connect with a random state,
 * confirms by email, and comes back here with a one-time code and the state.
 * The code is exchanged for the site's token server to server, so the token
 * never passes through the browser. Nothing is sent to Inkmeter before that.
 */
class SettingsPage {
	public const SLUG = 'inkmeter';

	public const ACTIONS = [ 'connect', 'disconnect', 'reminders', 'upgrade', 'manage' ];

	/**
	 * Plans the Upgrade buttons can ask the service to check out; the first is the default.
	 */
	private const PLANS = [ 'monthly', 'yearly' ];

	private const CAPABILITY = 'manage_options';

	private const STATE_TTL = 1800;

	/**
	 * @var Hooks
	 */
	private $hooks;

	/**
	 * @var Plugins
	 */
	private $plugins;

	/**
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * @var Nonces
	 */
	private $nonces;

	/**
	 * @var Redirects
	 */
	private $redirects;

	/**
	 * @var Urls
	 */
	private $urls;

	/**
	 * @var Transient
	 */
	private $transient;

	/**
	 * @var Users
	 */
	private $users;

	/**
	 * @var Passwords
	 */
	private $passwords;

	/**
	 * @var Dates
	 */
	private $dates;

	/**
	 * @var Errors
	 */
	private $errors;

	/**
	 * @var Sanitization
	 */
	private $sanitization;

	/**
	 * @var Formatting
	 */
	private $formatting;

	/**
	 * @var Api
	 */
	private $api;

	/**
	 * @var Connection
	 */
	private $connection;

	/**
	 * @var DaysSync
	 */
	private $sync;

	/**
	 * @var string
	 */
	private $site_url;

	/**
	 * Ends the request after a redirect; replaced in tests.
	 *
	 * @var callable
	 */
	private $terminate;

	/**
	 * @param string        $site_url  The Inkmeter website, which hosts the connect page.
	 * @param callable|null $terminate Called after redirecting; exits by default.
	 */
	public function __construct( Api $api, Connection $connection, DaysSync $sync, string $site_url, ?callable $terminate = null ) {
		$this->hooks        = ServiceFactory::create_hooks();
		$this->plugins      = ServiceFactory::create_plugins();
		$this->capabilities = ServiceFactory::create_capabilities();
		$this->nonces       = ServiceFactory::create_nonces();
		$this->redirects    = ServiceFactory::create_redirects();
		$this->urls         = ServiceFactory::create_urls();
		$this->transient    = ServiceFactory::create_transient();
		$this->users        = ServiceFactory::create_users();
		$this->passwords    = ServiceFactory::create_passwords();
		$this->dates        = ServiceFactory::create_dates();
		$this->errors       = ServiceFactory::create_errors();
		$this->sanitization = ServiceFactory::create_sanitization();
		$this->formatting   = ServiceFactory::create_formatting();
		$this->api          = $api;
		$this->connection   = $connection;
		$this->sync         = $sync;
		$this->site_url     = rtrim( $site_url, '/' );
		$this->terminate    = $terminate ?? static function (): void {
			exit;
		};
	}

	public function init(): void {
		$this->hooks->add_action( 'admin_menu', [ $this, 'add_page' ] );
		foreach ( self::ACTIONS as $action ) {
			$this->hooks->add_action( 'admin_post_inkmeter_' . $action, [ $this, $action ] );
		}
	}

	public function add_page(): void {
		$hook = $this->plugins->add_options_page(
			__( 'Inkmeter', 'inkmeter' ),
			__( 'Inkmeter', 'inkmeter' ),
			self::CAPABILITY,
			self::SLUG,
			[ $this, 'render' ]
		);

		if ( is_string( $hook ) && '' !== $hook ) {
			$this->hooks->add_action( 'load-' . $hook, [ $this, 'handle_return' ] );
		}
	}

	/**
	 * @param array<string, string> $args Query arguments to add.
	 */
	public function page_url( array $args = [] ): string {
		return (string) $this->urls->add_query_arg( $args, $this->urls->admin_url( 'options-general.php?page=' . self::SLUG ) );
	}

	/**
	 * Sends the admin to streakfire.org to confirm the connection by email.
	 */
	public function connect(): void {
		$this->authorize( 'connect' );

		$state = (string) $this->passwords->wp_generate_password( 32, false );
		$this->transient->set_transient( $this->state_key(), $state, self::STATE_TTL );

		// add_query_arg() doesn't encode values.
		$url = (string) $this->urls->add_query_arg(
			[
				'site_url'   => rawurlencode( $this->home_url() ),
				'return_url' => rawurlencode( $this->page_url() ),
				'state'      => rawurlencode( $state ),
			],
			$this->site_url . '/connect'
		);

		$this->redirect_away( $url );
	}

	/**
	 * Finishes connecting when streakfire.org sends the admin back with a code.
	 * Runs when the settings page loads, before any output.
	 */
	public function handle_return(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The state, checked below, protects this request.
		$code  = $this->input( $_GET, 'streakfire_code' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by input().
		$state = $this->input( $_GET, 'streakfire_state' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by input().
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' === $code || ! $this->capabilities->current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$expected = $this->transient->get_transient( $this->state_key() );
		$this->transient->delete_transient( $this->state_key() );
		if ( ! is_string( $expected ) || '' === $state || ! hash_equals( $expected, $state ) ) {
			$this->back( 'connect_failed' );
			return;
		}

		$response = $this->api->request(
			'POST',
			'/v1/connect/exchange',
			null,
			[
				'code'     => $code,
				'site_url' => $this->home_url(),
				'timezone' => (string) $this->dates->wp_timezone_string(),
			]
		);
		$token    = $response['data']['token'] ?? null;
		if ( null === $response || 200 !== $response['status'] || ! is_string( $token ) || '' === $token ) {
			$this->back( 'connect_failed' );
			return;
		}

		$entitlements = $response['data']['entitlements'] ?? [];
		$this->connection->connect( $token, (string) ( $response['data']['email'] ?? '' ), is_array( $entitlements ) ? $entitlements : [] );
		$this->sync->start();

		$this->back( 'connected' );
	}

	public function disconnect(): void {
		$this->authorize( 'disconnect' );

		$token = $this->connection->token();
		if ( null !== $token ) {
			// Best effort: the token is forgotten here either way.
			$this->api->request( 'DELETE', '/v1/site', $token );
		}
		$this->connection->forget();
		$this->sync->stop();

		$this->back( 'disconnected' );
	}

	public function reminders(): void {
		$this->authorize( 'reminders' );

		$hour = (int) $this->input( $_POST, 'reminder_hour' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked by authorize(); sanitized by input().
		$body = [
			'reminder_enabled' => '1' === $this->input( $_POST, 'reminder_enabled' ), // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked by authorize(); sanitized by input().
			'reminder_hour'    => max( 0, min( 23, $hour ) ),
		];
		// Only when the form had the checkbox: an unchecked box sends nothing, like a missing one.
		if ( $this->connection->has_feature( Connection::FEATURE_RECAPS ) ) {
			$body['recap_enabled'] = '1' === $this->input( $_POST, 'recap_enabled' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked by authorize(); sanitized by input().
		}

		$response = $this->call( 'PATCH', '/v1/settings', $body );
		if ( null !== $response ) {
			$this->back( 200 === $response['status'] ? 'saved' : 'error' );
		}
	}

	/**
	 * Opens the Lemon Squeezy checkout for this account.
	 */
	public function upgrade(): void {
		$this->authorize( 'upgrade' );

		$plan = $this->input( $_POST, 'plan' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked by authorize(); sanitized by input().
		$this->open_billing_link( 'POST', '/v1/checkout', [ 'plan' => in_array( $plan, self::PLANS, true ) ? $plan : self::PLANS[0] ] );
	}

	/**
	 * Prints a checkout button for each plan in the offer.
	 *
	 * @param array<int, array{id: string, price: string}> $plans
	 */
	public function render_plan_buttons( array $plans ): void {
		foreach ( $plans as $index => $plan ) {
			$label = 'yearly' === $plan['id']
				/* translators: %s: Yearly price, e.g. "$50/year". */
				? sprintf( __( 'Yearly, %s (2 months free)', 'inkmeter' ), $plan['price'] )
				/* translators: %s: Monthly price, e.g. "$5/month". */
				: sprintf( __( 'Monthly, %s', 'inkmeter' ), $plan['price'] );
			printf(
				'<button type="submit" name="plan" value="%1$s" class="button%2$s">%3$s</button> ',
				esc_attr( $plan['id'] ),
				0 === $index ? ' button-primary' : '',
				esc_html( $label )
			);
		}
	}

	/**
	 * Opens the Lemon Squeezy customer portal: card, plan, cancellation.
	 */
	public function manage(): void {
		$this->authorize( 'manage' );
		$this->open_billing_link( 'GET', '/v1/billing/portal' );
	}

	public function render(): void {
		if ( ! $this->capabilities->current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$notice       = $this->input( $_GET, 'inkmeter_notice' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only selects a message; sanitized by input().
		$is_reachable = true;
		$settings     = [];
		$streak       = null;
		$streak_unit  = 'day';
		$offer        = [
			'trial_days' => 0,
			'plans'      => [],
		];

		if ( $this->connection->is_connected() ) {
			$entitlements = $this->call_quietly( 'GET', '/v1/entitlements' );
			if ( ! $this->connection->is_connected() ) {
				$notice = 'revoked';
			} elseif ( null === $entitlements ) {
				$is_reachable = false;
			} elseif ( 200 === $entitlements['status'] ) {
				$this->connection->update_entitlements( $entitlements['data'] );
				$settings = $this->call_quietly( 'GET', '/v1/settings' )['data'] ?? [];
				if ( ! $this->connection->has_feature( Connection::FEATURE_REMINDERS ) ) {
					$offer = $this->offer();
				}
				$streak_data = $this->call_quietly( 'GET', '/v1/streak' )['data'] ?? null;
				if ( is_array( $streak_data ) ) {
					$this->connection->record_streak( $streak_data );
					$streak      = $streak_data['current'] ?? null;
					$streak_unit = (string) ( $streak_data['unit'] ?? 'day' );
				}
			}
		}

		[ $notice_type, $notice_message ] = $this->notice( $notice );

		$is_connected     = $this->connection->is_connected();
		$email            = $this->connection->email();
		$is_pro           = $this->connection->has_feature( Connection::FEATURE_REMINDERS );
		$freezes_left     = $this->connection->has_feature( Connection::FEATURE_FREEZES ) ? $this->connection->freezes_left() : null;
		$reminder_enabled = (bool) ( $settings['reminder_enabled'] ?? true );
		$reminder_hour    = (int) ( $settings['reminder_hour'] ?? 19 );
		$has_recaps       = $this->connection->has_feature( Connection::FEATURE_RECAPS );
		$recap_enabled    = (bool) ( $settings['recap_enabled'] ?? true );
		$timezone         = (string) $this->dates->wp_timezone_string();
		$synced_at        = $this->connection->last_synced_at();
		$synced_ago       = null === $synced_at ? null : (string) $this->dates->human_time_diff( $synced_at, time() );
		$streak           = is_int( $streak ) ? $streak : null;
		$site_url         = $this->home_url();
		$privacy_url      = $this->site_url . '/privacy';
		$action_url       = $this->urls->admin_url( 'admin-post.php' );

		include dirname( __DIR__ ) . '/views/settings_page.php';
	}

	/**
	 * Prints what Inkmeter Pro adds, for the pitch before connecting and the upgrade section.
	 */
	public function render_pro_features(): void {
		$features = [
			__( 'You get an email at the hour you choose when your goal needs a post that day.', 'inkmeter' ),
			__( 'Two streak freezes a month cover a missed day (or a week, with a weekly goal), so your streak lives on.', 'inkmeter' ),
			__( 'Every week, an email recaps the days you wrote, your streak and streak freezes left.', 'inkmeter' ),
			__( 'Your streak history is kept in your Inkmeter account, so it survives reinstalls and site moves, and the history calendar includes all your connected sites.', 'inkmeter' ),
		];

		echo '<ul class="ul-disc">';
		foreach ( $features as $feature ) {
			printf( '<li>%s</li>', esc_html( $feature ) );
		}
		echo '</ul>';
	}

	/**
	 * Prints the reminder hour choices, 00:00 to 23:00.
	 */
	public function render_hour_options( int $selected ): void {
		for ( $hour = 0; $hour < 24; $hour++ ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $hour ),
				selected( $selected, $hour, false ),
				esc_html( sprintf( '%02d:00', $hour ) )
			);
		}
	}

	/**
	 * The message shown after an action redirects back here.
	 *
	 * @return array{0: string|null, 1: string} Notice type ('success', 'warning', 'error') or null, and the message.
	 */
	private function notice( string $key ): array {
		$notices = [
			'connected'           => [ 'success', __( 'Your site is connected to Inkmeter.', 'inkmeter' ) ],
			'disconnected'        => [ 'success', __( 'Your site is disconnected from Inkmeter. Your streak history is kept in your account.', 'inkmeter' ) ],
			'saved'               => [ 'success', __( 'Email settings saved.', 'inkmeter' ) ],
			'connect_failed'      => [ 'error', __( 'Connecting didn\'t work. The link may have expired; please try again.', 'inkmeter' ) ],
			'revoked'             => [ 'warning', __( 'This site is no longer connected to Inkmeter. Connect it again to keep using Pro.', 'inkmeter' ) ],
			'unreachable'         => [ 'error', __( 'Inkmeter couldn\'t be reached. Please try again in a minute.', 'inkmeter' ) ],
			'billing_unavailable' => [ 'error', __( 'Billing isn\'t available right now. Please try again later.', 'inkmeter' ) ],
			'error'               => [ 'error', __( 'Something went wrong. Please try again.', 'inkmeter' ) ],
		];

		return $notices[ $key ] ?? [ null, '' ];
	}

	/**
	 * Calls the API with the site's token, handling the outcomes every action
	 * shares. Returns null after redirecting the admin back with a notice.
	 *
	 * @param array<string, mixed>|null $body
	 *
	 * @return array{status: int, data: array<string, mixed>}|null
	 */
	private function call( string $method, string $path, ?array $body = null ): ?array {
		$response = $this->call_quietly( $method, $path, $body );

		if ( null === $response ) {
			$this->back( $this->connection->is_connected() ? 'unreachable' : 'revoked' );
			return null;
		}

		return $response;
	}

	/**
	 * Like call(), without redirecting: null when not connected, unreachable or revoked.
	 *
	 * @param array<string, mixed>|null $body
	 *
	 * @return array{status: int, data: array<string, mixed>}|null
	 */
	private function call_quietly( string $method, string $path, ?array $body = null ): ?array {
		$token = $this->connection->token();
		if ( null === $token ) {
			return null;
		}

		$response = $this->api->request( $method, $path, $token, $body );
		if ( null !== $response && 401 === $response['status'] ) {
			// Revoked elsewhere: this site is no longer connected.
			$this->connection->forget();
			$this->sync->stop();
			return null;
		}

		return $response;
	}

	/**
	 * The plans on sale, from the service: the trial length and the plans that can be bought.
	 *
	 * @return array{trial_days: int, plans: array<int, array{id: string, price: string}>}
	 */
	private function offer(): array {
		$data  = $this->call_quietly( 'GET', '/v1/plans' )['data'] ?? [];
		$plans = [];
		foreach ( (array) ( $data['plans'] ?? [] ) as $plan ) {
			if ( is_array( $plan ) && ! empty( $plan['available'] ) && in_array( $plan['id'] ?? null, self::PLANS, true ) ) {
				$plans[] = [
					'id'    => (string) $plan['id'],
					'price' => (string) ( $plan['price'] ?? '' ),
				];
			}
		}

		return [
			'trial_days' => (int) ( $data['trial_days'] ?? 0 ),
			'plans'      => $plans,
		];
	}

	/**
	 * @param array<string, mixed>|null $body
	 */
	private function open_billing_link( string $method, string $path, ?array $body = null ): void {
		$response = $this->call( $method, $path, $body );
		if ( null === $response ) {
			return;
		}

		$url = $response['data']['url'] ?? null;
		if ( 200 !== $response['status'] || ! is_string( $url ) || 0 !== strpos( $url, 'https://' ) ) {
			$this->back( 'billing_unavailable' );
			return;
		}

		$this->redirect_away( $url );
	}

	private function authorize( string $action ): void {
		if ( ! $this->capabilities->current_user_can( self::CAPABILITY ) ) {
			$this->errors->wp_die( esc_html__( 'Sorry, you are not allowed to manage Inkmeter.', 'inkmeter' ), '', [ 'response' => 403 ] );
		}
		$this->nonces->check_admin_referer( 'inkmeter_' . $action );
	}

	/**
	 * Returns to the settings page with a notice.
	 */
	private function back( string $notice ): void {
		$this->redirects->wp_safe_redirect( $this->page_url( [ 'inkmeter_notice' => $notice ] ) );
		( $this->terminate )();
	}

	/**
	 * Redirects to streakfire.org or Lemon Squeezy, which wp_safe_redirect() would refuse.
	 */
	private function redirect_away( string $url ): void {
		$this->redirects->wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Intentionally external: streakfire.org or Lemon Squeezy.
		( $this->terminate )();
	}

	private function state_key(): string {
		return 'inkmeter_connect_' . (int) $this->users->get_current_user_id();
	}

	private function home_url(): string {
		return (string) $this->urls->home_url();
	}

	/**
	 * @param array<string, mixed> $source $_GET or $_POST.
	 */
	private function input( array $source, string $key ): string {
		$value = $source[ $key ] ?? '';

		return is_string( $value ) ? (string) $this->sanitization->sanitize_text_field( $this->formatting->wp_unslash( $value ) ) : '';
	}
}
