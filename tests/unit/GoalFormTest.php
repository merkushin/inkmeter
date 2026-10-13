<?php declare( strict_types=1 );

namespace MerkushinTest\Inkmeter;

use Merkushin\Inkmeter\Goal;
use Merkushin\Inkmeter\GoalForm;
use Merkushin\Inkmeter\Streak;
use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\Service\Errors;
use Merkushin\Wpal\Service\Formatting;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\Service\Nonces;
use Merkushin\Wpal\Service\Redirects;
use Merkushin\Wpal\Service\Sanitization;
use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GoalFormTest extends TestCase {
	/**
	 * @var Streak&MockObject
	 */
	private $streak;

	/**
	 * @var Capabilities&MockObject
	 */
	private $capabilities;

	/**
	 * @var Nonces&MockObject
	 */
	private $nonces;

	/**
	 * @var Errors&MockObject
	 */
	private $errors;

	/**
	 * @var string[]
	 */
	private $redirects = [];

	/**
	 * @var int
	 */
	private $terminated = 0;

	protected function setUp(): void {
		$_POST = [];

		$this->streak       = $this->createMock( Streak::class );
		$this->capabilities = $this->createMock( Capabilities::class );
		$this->nonces       = $this->createMock( Nonces::class );
		$this->errors       = $this->createMock( Errors::class );

		$redirects = $this->createMock( Redirects::class );
		$redirects->method( 'wp_safe_redirect' )->willReturnCallback(
			function ( string $url ): bool {
				$this->redirects[] = $url;
				return true;
			}
		);
		$urls = $this->createMock( Urls::class );
		$urls->method( 'admin_url' )->willReturnCallback(
			function ( string $path = '' ): string {
				return 'https://example.com/wp-admin/' . $path;
			}
		);
		$sanitization = $this->createMock( Sanitization::class );
		$sanitization->method( 'sanitize_text_field' )->willReturnArgument( 0 );
		$formatting = $this->createMock( Formatting::class );
		$formatting->method( 'wp_unslash' )->willReturnArgument( 0 );

		ServiceFactory::set_custom_capabilities( $this->capabilities );
		ServiceFactory::set_custom_nonces( $this->nonces );
		ServiceFactory::set_custom_errors( $this->errors );
		ServiceFactory::set_custom_redirects( $redirects );
		ServiceFactory::set_custom_urls( $urls );
		ServiceFactory::set_custom_sanitization( $sanitization );
		ServiceFactory::set_custom_formatting( $formatting );
	}

	protected function tearDown(): void {
		$_POST = [];
		foreach ( [ 'capabilities', 'nonces', 'errors', 'redirects', 'urls', 'sanitization', 'formatting', 'hooks' ] as $service ) {
			ServiceFactory::{'set_custom_' . $service}( null );
		}
	}

	public function testInit_Always_HandlesTheFormPost(): void {
		$hooks = $this->createMock( Hooks::class );
		ServiceFactory::set_custom_hooks( $hooks );
		$form = $this->create_form();

		$hooks->expects( $this->once() )->method( 'add_action' )->with( 'admin_post_inkmeter_goal', [ $form, 'save' ] );

		$form->init();
	}

	public function testSave_AdminChoosesWeekly_SavesAndGoesBack(): void {
		$this->capabilities->method( 'current_user_can' )->with( 'manage_options' )->willReturn( true );
		$this->nonces->expects( $this->once() )->method( 'check_admin_referer' )->with( 'inkmeter_goal' );
		$this->nonces->method( 'wp_get_referer' )->willReturn( 'https://example.com/wp-admin/edit.php?post_status=draft' );
		$_POST = [
			'inkmeter_goal'         => 'weekly-3',
			'inkmeter_count_drafts' => '1',
		];

		$this->streak
			->expects( $this->once() )
			->method( 'save_goal' )
			->with( $this->callback( fn( Goal $goal ): bool => 'weekly-3' === $goal->key() ) );
		$this->streak->expects( $this->once() )->method( 'save_counts_drafts' )->with( true );

		$this->create_form()->save();

		$this->assertSame( [ 'https://example.com/wp-admin/edit.php?post_status=draft' ], $this->redirects );
		$this->assertSame( 1, $this->terminated );
	}

	public function testSave_DraftsUnchecked_TurnsThemOff(): void {
		$this->capabilities->method( 'current_user_can' )->willReturn( true );
		$_POST = [ 'inkmeter_goal' => 'daily' ];

		$this->streak->expects( $this->once() )->method( 'save_counts_drafts' )->with( false );

		$this->create_form()->save();
	}

	public function testSave_InvalidGoal_SavesNothing(): void {
		$this->capabilities->method( 'current_user_can' )->willReturn( true );
		$_POST = [ 'inkmeter_goal' => 'weekly-9' ];

		$this->streak->expects( $this->never() )->method( 'save_goal' );

		$this->create_form()->save();

		$this->assertSame( [ 'https://example.com/wp-admin/edit.php' ], $this->redirects, 'without a referer it goes to the Posts screen' );
	}

	public function testSave_NotAnAdmin_Dies(): void {
		$this->capabilities->method( 'current_user_can' )->willReturn( false );
		$this->errors->expects( $this->once() )->method( 'wp_die' );

		$this->create_form()->save();
	}

	private function create_form(): GoalForm {
		return new GoalForm(
			$this->streak,
			function (): void {
				++$this->terminated;
			}
		);
	}
}
