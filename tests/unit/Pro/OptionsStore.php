<?php declare( strict_types=1 );

namespace MerkushinTest\Inkmeter\Pro;

use Merkushin\Wpal\Service\Options;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * An Options mock backed by an array, so Connection can be used for real.
 */
trait OptionsStore {
	/**
	 * @var array<string, mixed>
	 */
	private $stored_options = [];

	/**
	 * @return Options&MockObject
	 */
	private function create_options_store( TestCase $test ): Options {
		$options = $test->getMockBuilder( Options::class )->getMock();
		$options->method( 'get_option' )->willReturnCallback(
			function ( $name, $default_value = false ) {
				return $this->stored_options[ $name ] ?? $default_value;
			}
		);
		$options->method( 'update_option' )->willReturnCallback(
			function ( $name, $value ): bool {
				$this->stored_options[ $name ] = $value;
				return true;
			}
		);
		$options->method( 'delete_option' )->willReturnCallback(
			function ( $name ): bool {
				unset( $this->stored_options[ $name ] );
				return true;
			}
		);

		return $options;
	}
}
