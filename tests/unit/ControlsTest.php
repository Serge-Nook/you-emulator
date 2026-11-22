<?php
/**
 * Control layout tests.
 *
 * @package YouEmulator
 */

use WP_Mock\Tools\TestCase;

/**
 * Per-console controls.
 */
class ControlsTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		WP_Mock::passthruFunction( '__' );
		Youem_Settings::flush();
	}

	public function tearDown(): void {
		Youem_Settings::flush();
		parent::tearDown();
	}

	public function test_every_console_has_its_own_layout(): void {
		foreach ( array_keys( Youem_Consoles::all() ) as $code ) {
			$this->assertNotEmpty( Youem_Controls::layout( $code ), $code );
		}
		$labels = static function ( string $code ): array {
			$out = array();
			foreach ( Youem_Controls::layout( $code ) as $group ) {
				$out = array_merge( $out, array_values( $group['inputs'] ) );
			}
			return $out;
		};
		$this->assertSame( array( 4, 5, 6, 7, 8, 0, 2, 3 ), Youem_Controls::ids( 'nes' ) );
		$this->assertContains( 'Mode', $labels( 'segaMD' ) );
		$this->assertContains( 'C ↑', $labels( 'n64' ) );
		$this->assertContains( 'L2', $labels( 'psx' ) );
		$this->assertNotContains( 'L2', $labels( 'psp' ) );
		$this->assertArrayHasKey( 'rstick', Youem_Controls::layout( 'psx' ) );
		$this->assertArrayNotHasKey( 'stick', Youem_Controls::layout( 'snes' ) );
		$this->assertSame( 'BUTTON_1', Youem_Controls::defaults( 'psx' )[0]['pad'] );
		$this->assertSame( 'BUTTON_1', Youem_Controls::defaults( 'snes' )[8]['pad'] );
	}

	public function test_default_pad_buttons_are_unique_per_console(): void {
		foreach ( array_keys( Youem_Consoles::all() ) as $code ) {
			$pads = array_filter( array_column( Youem_Controls::defaults( $code ), 'pad' ) );
			$this->assertSame( count( $pads ), count( array_unique( $pads ) ), $code );
			$keys = array_filter( array_column( Youem_Controls::defaults( $code ), 'key' ) );
			$this->assertSame( count( $keys ), count( array_unique( $keys ) ), $code );
		}
	}

	public function test_sanitize_keeps_known_inputs_only(): void {
		$out = Youem_Controls::sanitize(
			array(
				'nes'  => array(
					8  => array(
						'key' => 'K',
						'pad' => 'BUTTON_1',
					),
					0  => array(
						'key' => 'not-a-key',
						'pad' => 'BUTTON_99',
					),
					12 => array(
						'key' => 'q',
						'pad' => 'LEFT_BOTTOM_SHOULDER',
					),
				),
				'evil' => array( 1 => array( 'key' => 'a' ) ),
			)
		);
		$this->assertSame(
			array(
				'key' => 'k',
				'pad' => 'BUTTON_1',
			),
			$out['nes'][8]
		);
		$this->assertSame(
			array(
				'key' => '',
				'pad' => '',
			),
			$out['nes'][0]
		);
		$this->assertArrayNotHasKey( 12, $out['nes'] );
		$this->assertArrayNotHasKey( 'evil', $out );
		$this->assertSame( 'enter', $out['nes'][3]['key'] );
		$this->assertSame( Youem_Controls::defaults( 'n64' ), $out['n64'] );
	}

	public function test_ejs_payload_uses_admin_mapping_for_player_one(): void {
		WP_Mock::userFunction( 'get_option' )->andReturn(
			array(
				'controls' => array(
					'gba' => array(
						8 => array(
							'key' => 'k',
							'pad' => 'BUTTON_2',
						),
					),
				),
			)
		);
		Youem_Settings::flush();
		$ejs = Youem_Controls::ejs( 'gba' );
		$this->assertCount( 4, $ejs );
		$p1 = (array) $ejs[0];
		$this->assertSame(
			array(
				'value'  => 'k',
				'value2' => 'BUTTON_2',
			),
			$p1[8]
		);
		$this->assertSame( 'x', $p1[0]['value'] );
		$this->assertArrayNotHasKey( 16, $p1 );
		$this->assertSame( array(), (array) $ejs[1] );
	}
}
