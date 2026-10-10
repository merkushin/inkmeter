<?php
/**
 * Smoke test for a release build: php tests/smoke/dist.php <build-dir>
 *
 * Loads the built plugin (scoped, with wpal pruned) using stand-ins for the WordPress
 * functions it calls, then runs every path: init, asset loading, rendering the panel,
 * clearing the cache, the Inkmeter Pro settings page and syncing published days.
 * A wpal class missing from the build fails here with a fatal error.
 *
 * @package Merkushin\Inkmeter
 */

declare( strict_types=1 );

$build_dir = rtrim( $argv[1] ?? '', '/' );
$main_file = $build_dir . '/inkmeter.php';
if ( ! is_file( $main_file ) ) {
	fwrite( STDERR, "Usage: php tests/smoke/dist.php <build-dir>\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['smoke_actions']    = [];
$GLOBALS['smoke_enqueued']   = [];
$GLOBALS['smoke_transients'] = [];
$GLOBALS['smoke_options']    = [
	'date_format'   => 'F j, Y',
	'start_of_week' => '1',
];
$GLOBALS['smoke_requests']   = [];
$GLOBALS['smoke_cron']       = [];

function add_action( string $hook, callable $callback ): bool {
	$GLOBALS['smoke_actions'][ $hook ][] = $callback;
	return true;
}

function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	return add_action( $hook, $callback );
}

/**
 * Runs every callback on an action. Filters have one callback here, whose result is returned.
 *
 * @param mixed ...$args
 * @return mixed
 */
function smoke_do( string $hook, ...$args ) {
	$result = null;
	foreach ( $GLOBALS['smoke_actions'][ $hook ] ?? [] as $callback ) {
		$result = $callback( ...$args );
	}
	return $result;
}

/**
 * Passes $value through the filter's callbacks, as WordPress does.
 *
 * @param mixed $value
 * @param mixed ...$args
 * @return mixed
 */
function apply_filters( string $hook, $value, ...$args ) {
	foreach ( $GLOBALS['smoke_actions'][ $hook ] ?? [] as $callback ) {
		$value = $callback( $value, ...$args );
	}
	return $value;
}

function register_deactivation_hook( string $file, callable $callback ): void {
	$GLOBALS['smoke_actions']['deactivate'][] = $callback;
}

function add_options_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '' ): string {
	add_action( 'smoke_render_settings', $callback );
	return 'settings_page_' . $menu_slug;
}

function current_user_can( $capability ): bool {
	return true;
}

function get_current_user_id(): int {
	return 1;
}

function admin_url( string $path = '' ): string {
	return 'https://example.com/wp-admin/' . $path;
}

function home_url( string $path = '' ): string {
	return 'https://example.com' . $path;
}

function add_query_arg( ...$args ): string {
	$url = (string) $args[1];
	foreach ( (array) $args[0] as $key => $value ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value;
	}
	return $url;
}

function wp_timezone_string(): string {
	return 'America/Mexico_City';
}

function human_time_diff( $from, $to = 0 ): string {
	return '1 min';
}

function sanitize_text_field( $text ): string {
	return trim( (string) $text );
}

function wp_unslash( $value ) {
	return $value;
}

function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
	return json_encode( $value, $flags, $depth );
}

function is_wp_error( $thing ): bool {
	return false;
}

/**
 * Answers like the Inkmeter API, recording each request.
 */
function wp_remote_request( $url, $args = [] ): array {
	$GLOBALS['smoke_requests'][] = $args['method'] . ' ' . $url;
	$path                        = (string) wp_parse_url( $url, PHP_URL_PATH );
	$bodies                      = [
		'/v1/days'         => [
			'current'      => 3,
			'unit'         => 'day',
			'frozen'       => [ '2026-02-27' ],
			'freezes_left' => 1,
		],
		'/v1/entitlements' => [
			'plan'     => 'pro',
			'features' => [ 'reminders', 'freezes', 'recaps' ],
		],
		'/v1/settings'     => [
			'reminder_enabled' => true,
			'reminder_hour'    => 19,
			'recap_enabled'    => true,
		],
		'/v1/streak'       => [
			'current'      => 3,
			'unit'         => 'day',
			'frozen'       => [ '2026-02-27' ],
			'freezes_left' => 1,
		],
	];
	return [
		'code' => 200,
		'body' => json_encode( $bodies[ $path ] ?? [] ),
	];
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- This is the stand-in.
}

function wp_remote_retrieve_response_code( $response ) {
	return $response['code'];
}

function wp_remote_retrieve_body( $response ) {
	return $response['body'];
}

function wp_next_scheduled( $hook, $args = [] ) {
	return $GLOBALS['smoke_cron'][ $hook ] ?? false;
}

function wp_schedule_event( $timestamp, $recurrence, $hook, $args = [], $wp_error = false ): bool {
	$GLOBALS['smoke_cron'][ $hook ] = $timestamp;
	return true;
}

function wp_schedule_single_event( $timestamp, $hook, $args = [], $wp_error = false ): bool {
	$GLOBALS['smoke_cron'][ $hook ] = $timestamp;
	return true;
}

function wp_clear_scheduled_hook( $hook, $args = [], $wp_error = false ): int {
	unset( $GLOBALS['smoke_cron'][ $hook ] );
	return 1;
}

function update_option( $option, $value, $autoload = null ): bool {
	$GLOBALS['smoke_options'][ $option ] = $value;
	return true;
}

function delete_option( $option ): bool {
	unset( $GLOBALS['smoke_options'][ $option ] );
	return true;
}

function esc_url( string $url ): string {
	return htmlspecialchars( $url, ENT_QUOTES );
}

function esc_html__( string $text, string $domain = 'default' ): string {
	return esc_html( $text );
}

function selected( $selected, $current = true, bool $display = true ): string {
	$result = (string) $selected === (string) $current ? " selected='selected'" : '';
	if ( $display ) {
		echo $result;
	}
	return $result;
}

function wp_nonce_field( $action = -1 ): string {
	echo '<input type="hidden" name="_wpnonce" value="nonce" />';
	return '';
}

function get_user_setting( $name, $default_value = false ) {
	return $default_value;
}

function checked( $checked, $current = true, bool $display = true ): string {
	$result = (string) $checked === (string) $current ? " checked='checked'" : '';
	if ( $display ) {
		echo $result;
	}
	return $result;
}

function plugin_dir_url( string $file ): string {
	return 'https://example.com/wp-content/plugins/inkmeter/';
}

function get_current_screen() {
	return (object) [
		'id'        => 'edit-post',
		'post_type' => 'post',
	];
}

function wp_add_inline_script( string $handle, string $data, string $position = 'after' ): bool {
	$GLOBALS['smoke_inline'][ $handle ] = $data;
	return true;
}

function wp_set_script_translations( $handle, $domain = 'default', $path = '' ): bool {
	return true;
}

function register_rest_route( $route_namespace, $route, $args = [], $override = false ): bool {
	$GLOBALS['smoke_routes'][ $route_namespace . $route ] = $args;
	return true;
}

function wp_enqueue_style( string $handle, string $src = '' ): void {
	$GLOBALS['smoke_enqueued'][] = $src;
}

function wp_enqueue_script( string $handle, string $src = '' ): void {
	$GLOBALS['smoke_enqueued'][] = $src;
}

function current_time( $type ) {
	return '2026-03-02';
}

function get_transient( string $key ) {
	return $GLOBALS['smoke_transients'][ $key ] ?? false;
}

function set_transient( string $key, $value, int $expiration = 0 ): bool {
	$GLOBALS['smoke_transients'][ $key ] = $value;
	return true;
}

function delete_transient( string $key ): bool {
	unset( $GLOBALS['smoke_transients'][ $key ] );
	return true;
}

function get_post_type( $post = null ) {
	return 'post';
}

function get_option( $option, $default_value = false ) {
	return $GLOBALS['smoke_options'][ $option ] ?? $default_value;
}

function wp_date( $format, $timestamp = null, $timezone = null ) {
	return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone )->format( $format );
}

function number_format_i18n( $number, $decimals = 0 ): string {
	return number_format( (float) $number, $decimals );
}

function __( string $text, string $domain = 'default' ): string {
	return $text;
}

function _n( string $single, string $plural, int $number, string $domain = 'default' ): string {
	return 1 === $number ? $single : $plural;
}

function _nx( string $single, string $plural, int $number, string $context, string $domain = 'default' ): string {
	return 1 === $number ? $single : $plural;
}

function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES );
}

function esc_attr( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES );
}

function esc_html_e( string $text, string $domain = 'default' ): void {
	echo esc_html( $text );
}

// The one query, in PublishedPostDates.
$GLOBALS['wpdb'] = new class() {
	/**
	 * @var string
	 */
	public $posts = 'wp_posts';

	public function get_col( string $query ): array {
		return [ '2026-03-02', '2026-03-01', '2026-02-28' ];
	}
};

/**
 * Stops the smoke test with a message.
 */
function smoke_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

require $main_file;

if ( ! isset( $GLOBALS['smoke_actions']['init'], $GLOBALS['smoke_actions']['deactivate'] ) ) {
	smoke_fail( 'the plugin did not hook into init and deactivation' );
}
smoke_do( 'init' );

foreach ( [ 'admin_enqueue_scripts', 'all_admin_notices', 'screen_settings', 'save_post', 'delete_post', 'admin_menu', 'inkmeter_sync_days', 'inkmeter_sync_days_daily', 'admin_post_inkmeter_connect', 'admin_post_inkmeter_goal', 'enqueue_block_editor_assets', 'rest_api_init' ] as $hook ) {
	if ( ! isset( $GLOBALS['smoke_actions'][ $hook ] ) ) {
		smoke_fail( "no callback for {$hook}" );
	}
}

smoke_do( 'admin_enqueue_scripts' );
if ( 2 !== count( $GLOBALS['smoke_enqueued'] ) ) {
	smoke_fail( 'expected 2 enqueued assets, got ' . count( $GLOBALS['smoke_enqueued'] ) );
}

ob_start();
smoke_do( 'all_admin_notices' );
$text = trim( (string) preg_replace( '/\s+/', ' ', strip_tags( (string) ob_get_clean() ) ) );
foreach ( [ 'Current run 3 days', 'Last published March 2, 2026', 'Next milestone 7 days', 'Published today', 'Get an email before your streak breaks', 'Publish every day', 'Change goal', 'Last 3 months', 'Published on 3 of' ] as $expected ) {
	if ( false === strpos( $text, $expected ) ) {
		smoke_fail( "panel is missing \"{$expected}\": {$text}" );
	}
}

// A weekly goal: March 2, 2026 is a Monday, so this week has one day with a post so far.
$GLOBALS['smoke_options']['inkmeter_goal'] = [
	'type' => 'weekly',
	'days' => 2,
];
$GLOBALS['smoke_transients']               = [];
ob_start();
smoke_do( 'all_admin_notices' );
$weekly = trim( (string) preg_replace( '/\s+/', ' ', strip_tags( (string) ob_get_clean() ) ) );
foreach ( [ 'Publish on 2 days a week', 'This week 1 of 2 days', '1 more day this week' ] as $expected ) {
	if ( false === strpos( $weekly, $expected ) ) {
		smoke_fail( "weekly panel is missing \"{$expected}\": {$weekly}" );
	}
}
unset( $GLOBALS['smoke_options']['inkmeter_goal'] );
$GLOBALS['smoke_transients'] = [];

// The block editor: the streak script with its settings, and the REST route it refreshes from.
$GLOBALS['smoke_enqueued'] = [];
smoke_do( 'enqueue_block_editor_assets' );
if ( [ 'https://example.com/wp-content/plugins/inkmeter/assets/dist/javascript/editor.js' ] !== $GLOBALS['smoke_enqueued'] || false === strpos( $GLOBALS['smoke_inline']['inkmeter-editor'] ?? '', '"path":"\/inkmeter\/v1\/streak"' ) ) {
	smoke_fail( 'the editor script or its settings are missing' );
}
smoke_do( 'rest_api_init' );
$route = $GLOBALS['smoke_routes']['inkmeter/v1/streak'] ?? null;
if ( null === $route || 3 !== ( $route['callback'] )()['streak'] ) {
	smoke_fail( 'the streak REST route is missing or wrong' );
}

$screen_settings = (string) smoke_do( 'screen_settings', '', get_current_screen() );
if ( false === strpos( $screen_settings, 'id="inkmeter-panel-toggle"' ) ) {
	smoke_fail( 'the Screen Options toggle is missing' );
}

smoke_do( 'save_post', 1 );
if ( [] !== $GLOBALS['smoke_transients'] ) {
	smoke_fail( 'save_post did not clear the cache' );
}
if ( [] !== $GLOBALS['smoke_cron'] || [] !== $GLOBALS['smoke_requests'] ) {
	smoke_fail( 'a site that is not connected scheduled a sync or called the API' );
}

// Inkmeter Pro, not connected: the settings page offers to connect.
smoke_do( 'admin_menu' );
ob_start();
smoke_do( 'smoke_render_settings' );
$settings_page = (string) ob_get_clean();
if ( false === strpos( $settings_page, 'value="inkmeter_connect"' ) || [] !== $GLOBALS['smoke_requests'] ) {
	smoke_fail( 'the settings page should offer to connect without calling the API' );
}

// Connected: a post change schedules a sync, which sends the published days.
update_option(
	'inkmeter_connection',
	[
		'token' => 'sfs_smoke',
		'email' => 'writer@example.com',
	]
);
smoke_do( 'save_post', 1 );
if ( ! isset( $GLOBALS['smoke_cron']['inkmeter_sync_days'] ) ) {
	smoke_fail( 'save_post did not schedule a sync' );
}
smoke_do( 'inkmeter_sync_days' );
if ( [ 'PUT https://api.streakfire.org/v1/days' ] !== $GLOBALS['smoke_requests'] || ! isset( $GLOBALS['smoke_options']['inkmeter_connection']['synced_at'] ) ) {
	smoke_fail( 'the sync did not send the days: ' . implode( ', ', $GLOBALS['smoke_requests'] ) );
}

ob_start();
smoke_do( 'smoke_render_settings' );
$settings_page = trim( (string) preg_replace( '/\s+/', ' ', strip_tags( (string) ob_get_clean() ) ) );
foreach ( [ 'writer@example.com', 'Pro', 'Streak in your account 3 days', 'Streak freezes 1 left this month', 'Last synced 1 min ago', 'a recap of my writing week', 'Save email settings', 'Manage subscription' ] as $expected ) {
	if ( false === strpos( $settings_page, $expected ) ) {
		smoke_fail( "settings page is missing \"{$expected}\": {$settings_page}" );
	}
}

smoke_do( 'deactivate' );
if ( [] !== $GLOBALS['smoke_cron'] ) {
	smoke_fail( 'deactivating left syncs scheduled' );
}

// Only the prefixed copy of wpal may be loaded, never the original namespace.
foreach ( array_merge( get_declared_classes(), get_declared_interfaces() ) as $class ) {
	if ( 0 === strpos( $class, 'Merkushin\\Wpal\\' ) ) {
		smoke_fail( "unprefixed wpal class loaded: {$class}" );
	}
}

echo "Smoke test passed: {$text}\n";
