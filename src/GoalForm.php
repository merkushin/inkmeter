<?php declare( strict_types=1 );

namespace Merkushin\Inkmeter;

use Merkushin\Wpal\Service\Capabilities;
use Merkushin\Wpal\Service\Errors;
use Merkushin\Wpal\Service\Formatting;
use Merkushin\Wpal\Service\Hooks;
use Merkushin\Wpal\Service\Nonces;
use Merkushin\Wpal\Service\Redirects;
use Merkushin\Wpal\Service\Sanitization;
use Merkushin\Wpal\Service\Urls;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Saves the goal chosen in the streak panel. The goal is site-wide, so only
 * administrators can change it.
 */
class GoalForm {
	public const ACTION = 'inkmeter_goal';

	public const FIELD = 'inkmeter_goal';

	public const DRAFTS_FIELD = 'inkmeter_count_drafts';

	private const CAPABILITY = 'manage_options';

	/**
	 * @var Hooks
	 */
	private $hooks;

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
	 * @var Streak
	 */
	private $streak;

	/**
	 * Ends the request after redirecting; replaced in tests.
	 *
	 * @var callable
	 */
	private $terminate;

	public function __construct( Streak $streak, ?callable $terminate = null ) {
		$this->hooks        = ServiceFactory::create_hooks();
		$this->capabilities = ServiceFactory::create_capabilities();
		$this->nonces       = ServiceFactory::create_nonces();
		$this->redirects    = ServiceFactory::create_redirects();
		$this->urls         = ServiceFactory::create_urls();
		$this->errors       = ServiceFactory::create_errors();
		$this->sanitization = ServiceFactory::create_sanitization();
		$this->formatting   = ServiceFactory::create_formatting();
		$this->streak       = $streak;
		$this->terminate    = $terminate ?? static function (): void {
			exit;
		};
	}

	public function init(): void {
		$this->hooks->add_action( 'admin_post_' . self::ACTION, [ $this, 'save' ] );
	}

	public function can_change(): bool {
		return (bool) $this->capabilities->current_user_can( self::CAPABILITY );
	}

	/**
	 * Where the panel's form posts to.
	 */
	public function action_url(): string {
		return (string) $this->urls->admin_url( 'admin-post.php' );
	}

	public function save(): void {
		if ( ! $this->can_change() ) {
			$this->errors->wp_die( esc_html__( 'Sorry, you are not allowed to change the writing goal.', 'inkmeter' ), '', [ 'response' => 403 ] );
		}
		$this->nonces->check_admin_referer( self::ACTION );

		$goal = Goal::from_key( $this->input( $_POST, self::FIELD ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked above; sanitized by input().
		if ( null !== $goal ) {
			$this->streak->save_goal( $goal );
		}
		$this->streak->save_counts_drafts( '1' === $this->input( $_POST, self::DRAFTS_FIELD ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked above; sanitized by input().

		// Back to the Posts screen, with any filters it had.
		$back = $this->nonces->wp_get_referer();
		$this->redirects->wp_safe_redirect( is_string( $back ) && '' !== $back ? $back : (string) $this->urls->admin_url( 'edit.php' ) );
		( $this->terminate )();
	}

	/**
	 * @param array<string, mixed> $source $_POST.
	 */
	private function input( array $source, string $key ): string {
		$value = $source[ $key ] ?? '';

		return is_string( $value ) ? (string) $this->sanitization->sanitize_text_field( $this->formatting->wp_unslash( $value ) ) : '';
	}
}
