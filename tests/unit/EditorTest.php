<?php declare( strict_types=1 );

namespace MerkushinTest\Inkmeter;

use Merkushin\Inkmeter\AssetVersions;
use Merkushin\Inkmeter\Editor;
use Merkushin\Inkmeter\Streak;
use Merkushin\Wpal\Service\Assets;
use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\Service\Json;
use Merkushin\Wpal\Service\Plugins;
use Merkushin\Wpal\Service\RestApi;
use Merkushin\Wpal\Service\Screen;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EditorTest extends TestCase {
	private const PLUGIN_FILE = '/plugins/inkmeter/inkmeter.php';

	private const SUMMARY = [
		'streak'          => 7,
		'unit'            => 'day',
		'last_post_date'  => '2026-03-01',
		'is_active_today' => false,
		'is_goal_met'     => false,
		'goal_days'       => 1,
		'period_days'     => 0,
		'days_left'       => 1,
		'saved_by_freeze' => false,
		'next_milestone'  => 14,
	];

	/**
	 * @var Assets&MockObject
	 */
	private $assets;

	/**
	 * @var Screen&MockObject
	 */
	private $screen;

	/**
	 * @var Capabilities&MockObject
	 */
	private $capabilities;

	/**
	 * @var RestApi&MockObject
	 */
	private $rest_api;

	/**
	 * @var Streak&MockObject
	 */
	private $streak;

	protected function setUp(): void {
		$this->assets       = $this->createMock( Assets::class );
		$this->screen       = $this->createMock( Screen::class );
		$this->capabilities = $this->createMock( Capabilities::class );
		$this->rest_api     = $this->createMock( RestApi::class );
		$this->streak       = $this->createMock( Streak::class );
		$this->streak->method( 'get_summary' )->willReturn( self::SUMMARY );

		$plugins = $this->createMock( Plugins::class );
		$plugins->method( 'plugin_dir_url' )->willReturn( 'https://example.com/wp-content/plugins/inkmeter/' );
		$json = $this->createMock( Json::class );
		$json->method( 'wp_json_encode' )->willReturnCallback( 'json_encode' );

		ServiceFactory::set_custom_assets( $this->assets );
		ServiceFactory::set_custom_screen( $this->screen );
		ServiceFactory::set_custom_capabilities( $this->capabilities );
		ServiceFactory::set_custom_rest_api( $this->rest_api );
		ServiceFactory::set_custom_plugins( $plugins );
		ServiceFactory::set_custom_json( $json );
	}

	protected function tearDown(): void {
		foreach ( [ 'assets', 'screen', 'capabilities', 'rest_api', 'plugins', 'json', 'hooks' ] as $service ) {
			ServiceFactory::{'set_custom_' . $service}( null );
		}
	}

	public function testInit_Always_HooksIntoTheEditorAndTheRestApi(): void {
		$hooks = $this->createMock( Hooks::class );
		ServiceFactory::set_custom_hooks( $hooks );
		$editor = $this->create_editor();

		$registered = [];
		$hooks->method( 'add_action' )->willReturnCallback(
			function ( string $hook, $callback ) use ( &$registered ): bool {
				$registered[ $hook ] = $callback;
				return true;
			}
		);

		$editor->init();

		$this->assertEquals(
			[
				'enqueue_block_editor_assets' => [ $editor, 'enqueue' ],
				'rest_api_init'               => [ $editor, 'register_routes' ],
			],
			$registered
		);
	}

	public function testEnqueue_EditingAPost_LoadsTheScriptWithTheStreak(): void {
		$this->screen->method( 'get_current_screen' )->willReturn( (object) [ 'post_type' => 'post' ] );
		$this->capabilities->method( 'current_user_can' )->with( 'edit_posts' )->willReturn( true );

		$this->assets
			->expects( $this->once() )
			->method( 'wp_enqueue_script' )
			->with(
				'inkmeter-editor',
				'https://example.com/wp-content/plugins/inkmeter/assets/dist/javascript/editor.js',
				$this->containsEqual( 'wp-plugins' ),
				'editor-hash',
				true
			);
		$this->assets
			->expects( $this->once() )
			->method( 'wp_add_inline_script' )
			->with(
				'inkmeter-editor',
				$this->callback(
					function ( string $code ): bool {
						$this->assertStringStartsWith( 'window.inkmeterEditor = ', $code );
						$settings = json_decode( substr( $code, strlen( 'window.inkmeterEditor = ' ), -1 ), true );
						$this->assertSame( '/inkmeter/v1/streak', $settings['path'] );
						$this->assertSame( 7, $settings['summary']['streak'] );
						$this->assertArrayNotHasKey( 'last_post_date', $settings['summary'], 'only the fields the editor uses' );
						return true;
					}
				),
				'before'
			);
		$this->assets->expects( $this->once() )->method( 'wp_set_script_translations' )->with( 'inkmeter-editor', 'inkmeter' );

		$this->create_editor()->enqueue();
	}

	/**
	 * @dataProvider provide_other_screens
	 *
	 * @param object|null $screen
	 */
	public function testEnqueue_OtherScreens_LoadsNothing( $screen ): void {
		$this->screen->method( 'get_current_screen' )->willReturn( $screen );
		$this->capabilities->method( 'current_user_can' )->willReturn( true );
		$this->assets->expects( $this->never() )->method( 'wp_enqueue_script' );

		$this->create_editor()->enqueue();
	}

	public function provide_other_screens(): array {
		return [
			'a page'    => [ (object) [ 'post_type' => 'page' ] ],
			'no screen' => [ null ],
		];
	}

	public function testEnqueue_UserCannotEditPosts_LoadsNothing(): void {
		$this->screen->method( 'get_current_screen' )->willReturn( (object) [ 'post_type' => 'post' ] );
		$this->capabilities->method( 'current_user_can' )->willReturn( false );
		$this->assets->expects( $this->never() )->method( 'wp_enqueue_script' );

		$this->create_editor()->enqueue();
	}

	public function testRegisterRoutes_Always_AddsTheStreakRouteForWriters(): void {
		$editor = $this->create_editor();
		$this->rest_api
			->expects( $this->once() )
			->method( 'register_rest_route' )
			->with(
				'inkmeter/v1',
				'/streak',
				[
					'methods'             => 'GET',
					'callback'            => [ $editor, 'summary' ],
					'permission_callback' => [ $editor, 'can_view' ],
				]
			);

		$editor->register_routes();
	}

	public function testSummary_Always_ReturnsTheEditorFields(): void {
		$this->assertSame(
			[ 'streak', 'unit', 'is_active_today', 'is_goal_met', 'goal_days', 'period_days', 'days_left', 'next_milestone' ],
			array_keys( $this->create_editor()->summary() )
		);
	}

	private function create_editor(): Editor {
		$asset_versions = $this->createMock( AssetVersions::class );
		$asset_versions->method( 'get' )->willReturnMap( [ [ 'javascript/editor.js', 'editor-hash' ] ] );

		return new Editor( $this->streak, self::PLUGIN_FILE, $asset_versions );
	}
}
