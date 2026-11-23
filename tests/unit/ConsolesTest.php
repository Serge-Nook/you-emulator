<?php
/**
 * Console registry tests.
 *
 * @package YouEmulator
 */

use WP_Mock\Tools\TestCase;

/**
 * Systems and cores.
 */
class ConsolesTest extends TestCase {

	public function test_required_systems(): void {
		foreach ( array( 'segaMD', 'snes', 'nes', 'gba', 'psx', 'psp', 'n64' ) as $code ) {
			$this->assertTrue( Youem_Consoles::exists( $code ), $code );
			$this->assertNotEmpty( Youem_Consoles::default_core( $code ) );
		}
		$this->assertFalse( Youem_Consoles::exists( 'dreamcast' ) );
	}

	public function test_psp_needs_threads(): void {
		$this->assertTrue( Youem_Consoles::get( 'psp' )['threads'] );
		$this->assertSame( 'ppsspp', Youem_Consoles::default_core( 'psp' ) );
		$this->assertContains( 'cue', Youem_Consoles::get( 'psx' )['extensions'] );
	}
}
