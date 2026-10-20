<?php declare( strict_types=1 );

namespace MerkushinTest\Inkmeter\Pro;

use Merkushin\Wpal\Service\Errors;
use Merkushin\Wpal\Service\Http;
use Merkushin\Wpal\Service\Json;
use Merkushin\Wpal\ServiceFactory;
use Merkushin\Inkmeter\Pro\Api;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase {
	/**
	 * @var Http&MockObject
	 */
	private $http;

	/**
	 * @var Errors&MockObject
	 */
	private $errors;

	protected function setUp(): void {
		$this->http   = $this->createMock( Http::class );
		$this->errors = $this->createMock( Errors::class );
		$json         = $this->createMock( Json::class );
		$json->method( 'wp_json_encode' )->willReturnCallback( 'json_encode' );

		ServiceFactory::set_custom_http( $this->http );
		ServiceFactory::set_custom_errors( $this->errors );
		ServiceFactory::set_custom_json( $json );
	}

	protected function tearDown(): void {
		ServiceFactory::set_custom_http( null );
		ServiceFactory::set_custom_errors( null );
		ServiceFactory::set_custom_json( null );
	}

	public function testRequest_WithTokenAndBody_SendsJsonWithBearerToken(): void {
		$this->http
			->expects( $this->once() )
			->method( 'wp_remote_request' )
			->with(
				'https://api.streakfire.test/v1/days',
				[
					'method'     => 'PUT',
					'timeout'    => 15,
					'user-agent' => 'Inkmeter/1.1.0',
					'body'       => '{"days":["2026-10-09"]}',
					'headers'    => [
						'Accept'        => 'application/json',
						'Authorization' => 'Bearer sfs_token',
						'Content-Type'  => 'application/json',
					],
				]
			)
			->willReturn( [ 'response' ] );
		$this->http->method( 'wp_remote_retrieve_response_code' )->willReturn( 200 );
		$this->http->method( 'wp_remote_retrieve_body' )->willReturn( '{"current":1}' );

		$result = ( new Api( 'https://api.streakfire.test/', 'Inkmeter/1.1.0' ) )->request( 'PUT', '/v1/days', 'sfs_token', [ 'days' => [ '2026-10-09' ] ] );

		$this->assertSame(
			[
				'status' => 200,
				'data'   => [ 'current' => 1 ],
			],
			$result
		);
	}

	public function testRequest_WithoutToken_SendsNoAuthorization(): void {
		$this->http
			->expects( $this->once() )
			->method( 'wp_remote_request' )
			->with(
				$this->anything(),
				$this->callback(
					function ( array $args ): bool {
						return ! isset( $args['headers']['Authorization'] ) && ! isset( $args['body'] );
					}
				)
			)
			->willReturn( [ 'response' ] );
		$this->http->method( 'wp_remote_retrieve_response_code' )->willReturn( 204 );
		$this->http->method( 'wp_remote_retrieve_body' )->willReturn( '' );

		$result = ( new Api( 'https://api.streakfire.test', 'Inkmeter/1.1.0' ) )->request( 'DELETE', '/v1/site' );

		$this->assertSame(
			[
				'status' => 204,
				'data'   => [],
			],
			$result
		);
	}

	public function testRequest_TransportError_ReturnsNull(): void {
		$this->http->method( 'wp_remote_request' )->willReturn( 'wp-error' );
		$this->errors->method( 'is_wp_error' )->with( 'wp-error' )->willReturn( true );

		$this->assertNull( ( new Api( 'https://api.streakfire.test', 'Inkmeter/1.1.0' ) )->request( 'GET', '/v1/streak', 'sfs_token' ) );
	}
}
