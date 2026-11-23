<?php
/**
 * Range parsing tests.
 *
 * @package YouEmulator
 */

use WP_Mock\Tools\TestCase;

/**
 * HTTP Range parsing.
 */
class StreamTest extends TestCase {

	public function test_ranges(): void {
		$this->assertSame( array( 0, 15 ), Youem_Stream::parse_range( 'bytes=0-15', 100 ) );
		$this->assertSame( array( 10, 99 ), Youem_Stream::parse_range( 'bytes=10-', 100 ) );
		$this->assertSame( array( 90, 99 ), Youem_Stream::parse_range( 'bytes=-10', 100 ) );
		$this->assertSame( array( 0, 99 ), Youem_Stream::parse_range( 'bytes=-1000', 100 ) );
		$this->assertSame( array( 50, 99 ), Youem_Stream::parse_range( 'bytes=50-5000', 100 ) );
	}

	public function test_unsatisfiable_and_ignored(): void {
		$this->assertFalse( Youem_Stream::parse_range( 'bytes=100-', 100 ) );
		$this->assertFalse( Youem_Stream::parse_range( 'bytes=20-10', 100 ) );
		$this->assertFalse( Youem_Stream::parse_range( 'bytes=-0', 100 ) );
		$this->assertNull( Youem_Stream::parse_range( '', 100 ) );
		$this->assertNull( Youem_Stream::parse_range( 'bytes=0-1,5-6', 100 ) );
		$this->assertNull( Youem_Stream::parse_range( 'items=0-1', 100 ) );
	}
}
