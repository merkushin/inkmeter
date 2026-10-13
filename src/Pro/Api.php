<?php declare( strict_types=1 );

namespace Merkushin\Inkmeter\Pro;

use Merkushin\Wpal\Service\Errors;
use Merkushin\Wpal\Service\Http;
use Merkushin\Wpal\Service\Json;
use Merkushin\Wpal\ServiceFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to the Inkmeter API. Only used once the site is connected to Inkmeter Pro.
 */
class Api {
	private const TIMEOUT = 15;

	/**
	 * @var Http
	 */
	private $http;

	/**
	 * @var Errors
	 */
	private $errors;

	/**
	 * @var Json
	 */
	private $json;

	/**
	 * @var string
	 */
	private $base_url;

	/**
	 * @var string
	 */
	private $user_agent;

	public function __construct( string $base_url, string $user_agent ) {
		$this->http       = ServiceFactory::create_http();
		$this->errors     = ServiceFactory::create_errors();
		$this->json       = ServiceFactory::create_json();
		$this->base_url   = rtrim( $base_url, '/' );
		$this->user_agent = $user_agent;
	}

	/**
	 * @param string                    $method HTTP method.
	 * @param string                    $path   Path such as '/v1/days'.
	 * @param string|null               $token  The site token, for everything but the connect exchange.
	 * @param array<string, mixed>|null $body   Sent as JSON.
	 *
	 * @return array{status: int, data: array<string, mixed>}|null Null when the request couldn't be made at all.
	 */
	public function request( string $method, string $path, ?string $token = null, ?array $body = null ): ?array {
		$headers = [ 'Accept' => 'application/json' ];
		$args    = [
			'method'     => $method,
			'timeout'    => self::TIMEOUT,
			'user-agent' => $this->user_agent,
		];
		if ( null !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		if ( null !== $body ) {
			$headers['Content-Type'] = 'application/json';
			$args['body']            = (string) $this->json->wp_json_encode( $body );
		}
		$args['headers'] = $headers;

		$response = $this->http->wp_remote_request( $this->base_url . $path, $args );
		if ( $this->errors->is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( (string) $this->http->wp_remote_retrieve_body( $response ), true );

		return [
			'status' => (int) $this->http->wp_remote_retrieve_response_code( $response ),
			'data'   => is_array( $data ) ? $data : [],
		];
	}
}
