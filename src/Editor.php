<?php declare( strict_types=1 );

namespace Merkushin\Inkmeter;

use Merkushin\Wpal\Service\Assets;
use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\Service\Json;
use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\Service\RestApi;
use Merkushin\Wpal\Service\Screen;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * The streak in the block editor, for posts: a pre-publish check, a line in
 * the Status panel, and a note after publishing that celebrates milestones
 * (assets/javascript/editor). The note reads the new streak from a REST route.
 */
class Editor {
	public const SCRIPT = 'inkmeter-editor';

	public const REST_NAMESPACE = 'inkmeter/v1';

	public const REST_ROUTE = '/streak';

	/**
	 * The summary fields the editor uses.
	 */
	private const FIELDS = [ 'streak', 'unit', 'is_active_today', 'is_goal_met', 'goal_days', 'period_days', 'days_left', 'next_milestone', 'counts_drafts' ];

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
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * @var RestApi
	 */
	private $rest_api;

	/**
	 * @var Json
	 */
	private $json;

	/**
	 * @var Streak
	 */
	private $streak;

	/**
	 * @var string
	 */
	private $plugin_file;

	/**
	 * @var AssetVersions
	 */
	private $asset_versions;

	public function __construct( Streak $streak, string $plugin_file, AssetVersions $asset_versions ) {
		$this->hooks          = ServiceFactory::create_hooks();
		$this->assets         = ServiceFactory::create_assets();
		$this->plugins        = ServiceFactory::create_plugins();
		$this->screen         = ServiceFactory::create_screen();
		$this->capabilities   = ServiceFactory::create_capabilities();
		$this->rest_api       = ServiceFactory::create_rest_api();
		$this->json           = ServiceFactory::create_json();
		$this->streak         = $streak;
		$this->plugin_file    = $plugin_file;
		$this->asset_versions = $asset_versions;
	}

	public function init(): void {
		$this->hooks->add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue' ] );
		$this->hooks->add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function enqueue(): void {
		$screen = $this->screen->get_current_screen();
		if ( ! is_object( $screen ) || 'post' !== ( $screen->post_type ?? null ) || ! $this->can_view() ) {
			return;
		}

		$url = $this->plugins->plugin_dir_url( $this->plugin_file );
		$this->assets->wp_enqueue_script(
			self::SCRIPT,
			$url . 'assets/dist/javascript/editor.js',
			[ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data', 'wp-i18n', 'wp-api-fetch', 'wp-notices' ],
			$this->asset_versions->get( 'javascript/editor.js' ),
			true
		);
		$settings = [
			'summary' => $this->summary(),
			'path'    => '/' . self::REST_NAMESPACE . self::REST_ROUTE,
		];
		$this->assets->wp_add_inline_script( self::SCRIPT, 'window.inkmeterEditor = ' . (string) $this->json->wp_json_encode( $settings ) . ';', 'before' );
		$this->assets->wp_set_script_translations( self::SCRIPT, 'inkmeter' );
	}

	public function register_routes(): void {
		$this->rest_api->register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'summary' ],
				'permission_callback' => [ $this, 'can_view' ],
			]
		);
	}

	/**
	 * Anyone who writes posts can see the site's streak.
	 */
	public function can_view(): bool {
		return (bool) $this->capabilities->current_user_can( 'edit_posts' );
	}

	/**
	 * @return array<string, mixed> The streak summary fields the editor uses.
	 */
	public function summary(): array {
		return array_intersect_key( $this->streak->get_summary(), array_flip( self::FIELDS ) );
	}
}
