<?php declare( strict_types=1 );

namespace Merkushin\Inkmeter;

defined( 'ABSPATH' ) || exit;

/**
 * The ?ver= of each built asset: a hash of its content, from assets/dist/versions.php,
 * which the build writes. A new build changes the URL, so browsers and caches
 * never keep serving an old file under the same plugin version.
 */
class AssetVersions {
	/**
	 * @var string
	 */
	private $dist_dir;

	/**
	 * Used when the build has no hash for a file, e.g. a checkout without versions.php.
	 *
	 * @var string
	 */
	private $fallback;

	/**
	 * @var array<string, string>|null
	 */
	private $versions;

	public function __construct( string $dist_dir, string $fallback ) {
		$this->dist_dir = $dist_dir;
		$this->fallback = $fallback;
	}

	/**
	 * @param string $file A path in assets/dist, e.g. "styles/admin.css".
	 */
	public function get( string $file ): string {
		if ( null === $this->versions ) {
			$this->versions = $this->load();
		}

		return $this->versions[ $file ] ?? $this->fallback;
	}

	/**
	 * @return array<string, string>
	 */
	private function load(): array {
		$path = $this->dist_dir . '/versions.php';
		if ( ! is_readable( $path ) ) {
			return [];
		}

		$versions = include $path;

		return is_array( $versions ) ? array_map( 'strval', $versions ) : [];
	}
}
