<?php declare( strict_types=1 );

namespace MerkushinTest\Inkmeter;

use Merkushin\Inkmeter\AssetVersions;
use PHPUnit\Framework\TestCase;

class AssetVersionsTest extends TestCase {
	/**
	 * @var string
	 */
	private $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/inkmeter-asset-versions-' . uniqid();
		mkdir( $this->dir );
	}

	protected function tearDown(): void {
		array_map( 'unlink', (array) glob( $this->dir . '/*' ) );
		rmdir( $this->dir );
	}

	public function testGet_FileInTheBuild_ReturnsItsHash(): void {
		file_put_contents( $this->dir . '/versions.php', "<?php\nreturn [ 'styles/admin.css' => 'b30724172b91fb97', 'javascript/admin.js' => '108f06f96fb5efed' ];\n" );

		$versions = new AssetVersions( $this->dir, '1.1.0' );

		$this->assertSame( 'b30724172b91fb97', $versions->get( 'styles/admin.css' ) );
		$this->assertSame( '108f06f96fb5efed', $versions->get( 'javascript/admin.js' ) );
	}

	public function testGet_FileNotInTheBuild_ReturnsTheFallback(): void {
		file_put_contents( $this->dir . '/versions.php', "<?php\nreturn [ 'styles/admin.css' => 'b30724172b91fb97' ];\n" );

		$this->assertSame( '1.1.0', ( new AssetVersions( $this->dir, '1.1.0' ) )->get( 'javascript/editor.js' ) );
	}

	public function testGet_NoVersionsFile_ReturnsTheFallback(): void {
		$this->assertSame( '1.1.0', ( new AssetVersions( $this->dir, '1.1.0' ) )->get( 'styles/admin.css' ) );
	}

	public function testGet_VersionsFileWithoutAnArray_ReturnsTheFallback(): void {
		file_put_contents( $this->dir . '/versions.php', "<?php\nreturn 'broken';\n" );

		$this->assertSame( '1.1.0', ( new AssetVersions( $this->dir, '1.1.0' ) )->get( 'styles/admin.css' ) );
	}
}
