<?php declare( strict_types=1 );

namespace Merkushin\Inkmeter;

use Merkushin\Wpal\Service\Options;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * The site's writing goal, the first day of its week, and whether drafts count.
 */
class GoalSettings {
	public const OPTION = 'inkmeter_goal';

	/**
	 * '1' when days spent on a draft count toward the streak, not only days with a published post.
	 */
	public const DRAFTS_OPTION = 'inkmeter_count_drafts';

	/**
	 * @var Options
	 */
	private $options;

	public function __construct() {
		$this->options = ServiceFactory::create_options();
	}

	/**
	 * The daily goal until another is chosen.
	 */
	public function goal(): Goal {
		return Goal::from_array( $this->options->get_option( self::OPTION, null ) );
	}

	public function save( Goal $goal ): void {
		// Autoloaded: the panel reads it on every visit to the Posts screen.
		$this->options->update_option( self::OPTION, $goal->to_array(), true );
	}

	/**
	 * Whether days spent on a draft count as writing days. Off until an administrator turns it on.
	 */
	public function counts_drafts(): bool {
		return '1' === (string) $this->options->get_option( self::DRAFTS_OPTION, '0' );
	}

	public function save_counts_drafts( bool $counts ): void {
		// Autoloaded, like the goal.
		$this->options->update_option( self::DRAFTS_OPTION, $counts ? '1' : '0', true );
	}

	/**
	 * WordPress' Settings → General → Week Starts On: 0 (Sunday) to 6.
	 */
	public function week_start(): int {
		$value = $this->options->get_option( 'start_of_week', 1 );

		return is_numeric( $value ) ? ( (int) $value % 7 + 7 ) % 7 : 1;
	}
}
