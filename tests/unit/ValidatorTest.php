<?php
/**
 * Validator tests.
 *
 * @package YouEmulator
 */

use WP_Mock\Tools\TestCase;

/**
 * ROM extension/signature checks.
 */
class ValidatorTest extends TestCase {

	/**
	 * Temp files.
	 *
	 * @var string[]
	 */
	private array $files = array();

	public function setUp(): void {
		parent::setUp();
		WP_Mock::passthruFunction( '__' );
		WP_Mock::userFunction( 'wp_basename', array( 'return' => static fn( $p ) => basename( $p ) ) );
	}

	public function tearDown(): void {
		foreach ( $this->files as $f ) {
			@unlink( $f );
		}
		parent::tearDown();
	}

	private function tmp( string $data ): string {
		$f             = tempnam( sys_get_temp_dir(), 'youem' );
		$this->files[] = $f;
		file_put_contents( $f, $data );
		return $f;
	}

	public function test_extension_allowed(): void {
		$this->assertTrue( Youem_Validator::extension_allowed( 'nes', 'Игра.NES' ) );
		$this->assertTrue( Youem_Validator::extension_allowed( 'psx', 'disc.cue' ) );
		$this->assertFalse( Youem_Validator::extension_allowed( 'nes', 'game.exe' ) );
		$this->assertFalse( Youem_Validator::extension_allowed( 'unknown', 'game.nes' ) );
	}

	public function test_signatures(): void {
		$this->assertTrue( Youem_Validator::header_matches( $this->tmp( "NES\x1A" . str_repeat( "\0", 16 ) ), 'nes' ) );
		$this->assertFalse( Youem_Validator::header_matches( $this->tmp( 'MZ' . str_repeat( "\0", 16 ) ), 'nes' ) );
		$this->assertTrue( Youem_Validator::header_matches( $this->tmp( "\x80\x37\x12\x40" ), 'z64' ) );
		$this->assertTrue( Youem_Validator::header_matches( $this->tmp( "PK\x03\x04xx" ), 'zip' ) );
		$this->assertTrue( Youem_Validator::header_matches( $this->tmp( "7z\xBC\xAF\x27\x1Cxx" ), '7z' ) );
		$gba = str_repeat( "\0", 0xB2 ) . "\x96" . str_repeat( "\0", 32 );
		$this->assertTrue( Youem_Validator::header_matches( $this->tmp( $gba ), 'gba' ) );
		$this->assertFalse( Youem_Validator::header_matches( $this->tmp( str_repeat( "\0", 256 ) ), 'gba' ) );
		$this->assertTrue( Youem_Validator::header_matches( $this->tmp( 'PS-X EXE' . str_repeat( "\0", 8 ) ), 'exe' ) );
		$this->assertFalse( Youem_Validator::header_matches( $this->tmp( "MZ\x90\0" . str_repeat( "\0", 8 ) ), 'exe' ) );
		$this->assertTrue( Youem_Validator::header_matches( $this->tmp( "FILE \"a.bin\" BINARY\n" ), 'cue' ) );
	}

	public function test_cue_files(): void {
		$cue = "FILE \"Track 01.bin\" BINARY\n  TRACK 01 MODE2/2352\nFILE dir\\track2.bin BINARY\n";
		$this->assertSame( array( 'Track 01.bin', 'track2.bin' ), Youem_Validator::cue_files( $cue ) );
	}
}
