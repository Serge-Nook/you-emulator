<?php
/**
 * Chunked upload tests.
 *
 * @package YouEmulator
 */

use WP_Mock\Tools\TestCase;

/**
 * Chunk storage and validation.
 */
class UploadTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::passthruFunction( '__' );
	}

	public function test_valid_id(): void {
		$this->assertTrue( Youem_Upload::valid_id( 'abcd-1234-ef' ) );
		$this->assertFalse( Youem_Upload::valid_id( '../../etc' ) );
		$this->assertFalse( Youem_Upload::valid_id( 'short' ) );
		$this->assertFalse( Youem_Upload::valid_id( str_repeat( 'a', 65 ) ) );
	}

	public function test_bad_chunk_params(): void {
		foreach ( array( array( 'bad/../id', 0, 1 ), array( 'abcd1234', -1, 2 ), array( 'abcd1234', 2, 2 ), array( 'abcd1234', 0, 0 ), array( 'abcd1234', 0, Youem_Upload::MAX_CHUNKS + 1 ) ) as $c ) {
			$r = Youem_Upload::put_chunk( $c[0], $c[1], $c[2], '/nonexistent' );
			$this->assertInstanceOf( WP_Error::class, $r );
			$this->assertSame( 'youem_bad_chunk', $r->get_error_code() );
		}
	}

	public function test_non_uploaded_file_rejected(): void {
		WP_Mock::onFilter( 'youem_allow_non_uploaded_chunk' )->with( false, __FILE__ )->reply( false );
		$r = Youem_Upload::put_chunk( 'abcd1234', 0, 1, __FILE__ );
		$this->assertInstanceOf( WP_Error::class, $r );
	}
}
