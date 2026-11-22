<?php
/**
 * Per-console control layouts (keyboard and gamepad) set by the administrator.
 *
 * Input ids follow the EmulatorJS/libretro RetroPad numbering used by
 * EJS_defaultControls.
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Control layouts.
 */
class Youem_Controls {

	/**
	 * EmulatorJS 4.2.3 default bindings for player 1: id => [keyboard key, gamepad input].
	 */
	private const DEFAULTS = array(
		0  => array( 'x', 'BUTTON_2' ),
		1  => array( 's', 'BUTTON_4' ),
		2  => array( 'v', 'SELECT' ),
		3  => array( 'enter', 'START' ),
		4  => array( 'up arrow', 'DPAD_UP' ),
		5  => array( 'down arrow', 'DPAD_DOWN' ),
		6  => array( 'left arrow', 'DPAD_LEFT' ),
		7  => array( 'right arrow', 'DPAD_RIGHT' ),
		8  => array( 'z', 'BUTTON_1' ),
		9  => array( 'a', 'BUTTON_3' ),
		10 => array( 'q', 'LEFT_TOP_SHOULDER' ),
		11 => array( 'e', 'RIGHT_TOP_SHOULDER' ),
		12 => array( 'tab', 'LEFT_BOTTOM_SHOULDER' ),
		13 => array( 'r', 'RIGHT_BOTTOM_SHOULDER' ),
		14 => array( '', 'LEFT_STICK' ),
		15 => array( '', 'RIGHT_STICK' ),
		16 => array( 'h', 'LEFT_STICK_X:+1' ),
		17 => array( 'f', 'LEFT_STICK_X:-1' ),
		18 => array( 'g', 'LEFT_STICK_Y:+1' ),
		19 => array( 't', 'LEFT_STICK_Y:-1' ),
		20 => array( 'l', 'RIGHT_STICK_X:+1' ),
		21 => array( 'j', 'RIGHT_STICK_X:-1' ),
		22 => array( 'k', 'RIGHT_STICK_Y:+1' ),
		23 => array( 'i', 'RIGHT_STICK_Y:-1' ),
	);

	/**
	 * Gamepad defaults that follow the button positions of the original controller.
	 */
	private const PAD_OVERRIDES = array(
		'segaMD' => array(
			1 => 'BUTTON_3',
			0 => 'BUTTON_1',
			8 => 'BUTTON_2',
			9 => 'BUTTON_4',
		),
		'psx'    => array(
			0 => 'BUTTON_1',
			8 => 'BUTTON_2',
			1 => 'BUTTON_3',
			9 => 'BUTTON_4',
		),
		'psp'    => array(
			0 => 'BUTTON_1',
			8 => 'BUTTON_2',
			1 => 'BUTTON_3',
			9 => 'BUTTON_4',
		),
		'n64'    => array(
			0 => 'BUTTON_1',
			1 => 'BUTTON_3',
		),
	);

	/**
	 * Keyboard key names understood by EmulatorJS (its keyMap).
	 */
	private const KEYS = array(
		'backspace',
		'tab',
		'enter',
		'shift',
		'ctrl',
		'alt',
		'pause/break',
		'caps lock',
		'escape',
		'space',
		'page up',
		'page down',
		'end',
		'home',
		'left arrow',
		'up arrow',
		'right arrow',
		'down arrow',
		'insert',
		'delete',
		'0',
		'1',
		'2',
		'3',
		'4',
		'5',
		'6',
		'7',
		'8',
		'9',
		'a',
		'b',
		'c',
		'd',
		'e',
		'f',
		'g',
		'h',
		'i',
		'j',
		'k',
		'l',
		'm',
		'n',
		'o',
		'p',
		'q',
		'r',
		's',
		't',
		'u',
		'v',
		'w',
		'x',
		'y',
		'z',
		'left window key',
		'right window key',
		'select key',
		'numpad 0',
		'numpad 1',
		'numpad 2',
		'numpad 3',
		'numpad 4',
		'numpad 5',
		'numpad 6',
		'numpad 7',
		'numpad 8',
		'numpad 9',
		'multiply',
		'add',
		'subtract',
		'decimal point',
		'divide',
		'f1',
		'f2',
		'f3',
		'f4',
		'f5',
		'f6',
		'f7',
		'f8',
		'f9',
		'f10',
		'f11',
		'f12',
		'num lock',
		'scroll lock',
		'semi-colon',
		'equal sign',
		'comma',
		'dash',
		'period',
		'forward slash',
		'grave accent',
		'open bracket',
		'back slash',
		'close braket',
		'single quote',
	);

	/**
	 * Allowed keyboard key names.
	 *
	 * @return string[]
	 */
	public static function keys(): array {
		return self::KEYS;
	}

	/**
	 * Gamepad inputs (standard mapping) => label shown in settings.
	 *
	 * @return array<string, string>
	 */
	public static function pad_inputs(): array {
		return array(
			'BUTTON_1'              => __( 'Bottom face button (A / ✕)', 'you-emulator' ),
			'BUTTON_2'              => __( 'Right face button (B / ○)', 'you-emulator' ),
			'BUTTON_3'              => __( 'Left face button (X / □)', 'you-emulator' ),
			'BUTTON_4'              => __( 'Top face button (Y / △)', 'you-emulator' ),
			'LEFT_TOP_SHOULDER'     => 'LB / L1',
			'RIGHT_TOP_SHOULDER'    => 'RB / R1',
			'LEFT_BOTTOM_SHOULDER'  => 'LT / L2',
			'RIGHT_BOTTOM_SHOULDER' => 'RT / R2',
			'SELECT'                => 'Back / Select',
			'START'                 => 'Start',
			'LEFT_STICK'            => __( 'Left stick press (L3)', 'you-emulator' ),
			'RIGHT_STICK'           => __( 'Right stick press (R3)', 'you-emulator' ),
			'DPAD_UP'               => __( 'D-pad up', 'you-emulator' ),
			'DPAD_DOWN'             => __( 'D-pad down', 'you-emulator' ),
			'DPAD_LEFT'             => __( 'D-pad left', 'you-emulator' ),
			'DPAD_RIGHT'            => __( 'D-pad right', 'you-emulator' ),
			'LEFT_STICK_Y:-1'       => __( 'Left stick up', 'you-emulator' ),
			'LEFT_STICK_Y:+1'       => __( 'Left stick down', 'you-emulator' ),
			'LEFT_STICK_X:-1'       => __( 'Left stick left', 'you-emulator' ),
			'LEFT_STICK_X:+1'       => __( 'Left stick right', 'you-emulator' ),
			'RIGHT_STICK_Y:-1'      => __( 'Right stick up', 'you-emulator' ),
			'RIGHT_STICK_Y:+1'      => __( 'Right stick down', 'you-emulator' ),
			'RIGHT_STICK_X:-1'      => __( 'Right stick left', 'you-emulator' ),
			'RIGHT_STICK_X:+1'      => __( 'Right stick right', 'you-emulator' ),
		);
	}

	/**
	 * Control layout of a console: groups of id => label, in the order of the original controller.
	 *
	 * @param string $console Console code.
	 * @return array<string, array{label: string, inputs: array<int, string>}>
	 */
	public static function layout( string $console ): array {
		$dpad   = array(
			4 => __( 'Up', 'you-emulator' ),
			5 => __( 'Down', 'you-emulator' ),
			6 => __( 'Left', 'you-emulator' ),
			7 => __( 'Right', 'you-emulator' ),
		);
		$stick  = array(
			19 => __( 'Up', 'you-emulator' ),
			18 => __( 'Down', 'you-emulator' ),
			17 => __( 'Left', 'you-emulator' ),
			16 => __( 'Right', 'you-emulator' ),
		);
		$rstick = array(
			23 => __( 'Up', 'you-emulator' ),
			22 => __( 'Down', 'you-emulator' ),
			21 => __( 'Left', 'you-emulator' ),
			20 => __( 'Right', 'you-emulator' ),
		);
		$g      = static function ( string $label, array $inputs ): array {
			return array(
				'label'  => $label,
				'inputs' => $inputs,
			);
		};
		$sys    = static function ( string $select = 'Select' ) use ( $g ): array {
			return $g(
				__( 'System buttons', 'you-emulator' ),
				array(
					2 => $select,
					3 => 'Start',
				)
			);
		};

		switch ( $console ) {
			case 'nes':
				return array(
					'dpad'   => $g( __( 'D-pad', 'you-emulator' ), $dpad ),
					'face'   => $g(
						__( 'Buttons', 'you-emulator' ),
						array(
							8 => 'A',
							0 => 'B',
						)
					),
					'system' => $sys(),
					'disk'   => $g(
						__( 'Famicom Disk System', 'you-emulator' ),
						array(
							10 => __( 'Swap disk side', 'you-emulator' ),
							11 => __( 'Eject/insert disk', 'you-emulator' ),
						)
					),
				);
			case 'snes':
				return array(
					'dpad'     => $g( __( 'D-pad', 'you-emulator' ), $dpad ),
					'face'     => $g(
						__( 'Buttons', 'you-emulator' ),
						array(
							8 => 'A',
							0 => 'B',
							9 => 'X',
							1 => 'Y',
						)
					),
					'shoulder' => $g(
						__( 'Shoulder buttons', 'you-emulator' ),
						array(
							10 => 'L',
							11 => 'R',
						)
					),
					'system'   => $sys(),
				);
			case 'segaMD':
				return array(
					'dpad'   => $g( __( 'D-pad', 'you-emulator' ), $dpad ),
					'face'   => $g(
						__( 'Buttons', 'you-emulator' ),
						array(
							1  => 'A',
							0  => 'B',
							8  => 'C',
							10 => 'X',
							9  => 'Y',
							11 => 'Z',
						)
					),
					'system' => $sys( 'Mode' ),
				);
			case 'gba':
				return array(
					'dpad'     => $g( __( 'D-pad', 'you-emulator' ), $dpad ),
					'face'     => $g(
						__( 'Buttons', 'you-emulator' ),
						array(
							8 => 'A',
							0 => 'B',
						)
					),
					'shoulder' => $g(
						__( 'Shoulder buttons', 'you-emulator' ),
						array(
							10 => 'L',
							11 => 'R',
						)
					),
					'system'   => $sys(),
				);
			case 'psx':
				return array(
					'dpad'     => $g( __( 'D-pad', 'you-emulator' ), $dpad ),
					'face'     => $g(
						__( 'Buttons', 'you-emulator' ),
						array(
							0 => '✕ ' . __( 'Cross', 'you-emulator' ),
							8 => '○ ' . __( 'Circle', 'you-emulator' ),
							1 => '□ ' . __( 'Square', 'you-emulator' ),
							9 => '△ ' . __( 'Triangle', 'you-emulator' ),
						)
					),
					'shoulder' => $g(
						__( 'Shoulder buttons', 'you-emulator' ),
						array(
							10 => 'L1',
							11 => 'R1',
							12 => 'L2',
							13 => 'R2',
							14 => 'L3',
							15 => 'R3',
						)
					),
					'system'   => $sys(),
					'stick'    => $g( __( 'Left analog stick (DualShock)', 'you-emulator' ), $stick ),
					'rstick'   => $g( __( 'Right analog stick (DualShock)', 'you-emulator' ), $rstick ),
				);
			case 'psp':
				return array(
					'dpad'     => $g( __( 'D-pad', 'you-emulator' ), $dpad ),
					'face'     => $g(
						__( 'Buttons', 'you-emulator' ),
						array(
							0 => '✕ ' . __( 'Cross', 'you-emulator' ),
							8 => '○ ' . __( 'Circle', 'you-emulator' ),
							1 => '□ ' . __( 'Square', 'you-emulator' ),
							9 => '△ ' . __( 'Triangle', 'you-emulator' ),
						)
					),
					'shoulder' => $g(
						__( 'Shoulder buttons', 'you-emulator' ),
						array(
							10 => 'L',
							11 => 'R',
						)
					),
					'system'   => $sys(),
					'stick'    => $g( __( 'Analog stick', 'you-emulator' ), $stick ),
				);
			case 'n64':
				return array(
					'dpad'     => $g( __( 'D-pad', 'you-emulator' ), $dpad ),
					'face'     => $g(
						__( 'Buttons', 'you-emulator' ),
						array(
							0 => 'A',
							1 => 'B',
						)
					),
					'cbuttons' => $g(
						__( 'C buttons', 'you-emulator' ),
						array(
							23 => 'C ↑',
							22 => 'C ↓',
							21 => 'C ←',
							20 => 'C →',
						)
					),
					'shoulder' => $g(
						__( 'Triggers', 'you-emulator' ),
						array(
							12 => 'Z',
							10 => 'L',
							11 => 'R',
						)
					),
					'system'   => $g( __( 'System buttons', 'you-emulator' ), array( 3 => 'Start' ) ),
					'stick'    => $g( __( 'Analog stick', 'you-emulator' ), $stick ),
				);
		}
		return array();
	}

	/**
	 * Input ids used by a console.
	 *
	 * @param string $console Console code.
	 * @return int[]
	 */
	public static function ids( string $console ): array {
		$ids = array();
		foreach ( self::layout( $console ) as $group ) {
			$ids = array_merge( $ids, array_keys( $group['inputs'] ) );
		}
		return $ids;
	}

	/**
	 * Default binding of a console.
	 *
	 * @param string $console Console code.
	 * @return array<int, array{key: string, pad: string}>
	 */
	public static function defaults( string $console ): array {
		$out = array();
		foreach ( self::ids( $console ) as $id ) {
			$out[ $id ] = array(
				'key' => self::DEFAULTS[ $id ][0],
				'pad' => self::PAD_OVERRIDES[ $console ][ $id ] ?? self::DEFAULTS[ $id ][1],
			);
		}
		return $out;
	}

	/**
	 * Effective binding of a console (administrator settings over defaults).
	 *
	 * @param string $console Console code.
	 * @return array<int, array{key: string, pad: string}>
	 */
	public static function mapping( string $console ): array {
		$stored = (array) ( Youem_Settings::get( 'controls' ) ?? array() );
		$out    = self::defaults( $console );
		foreach ( $out as $id => $binding ) {
			if ( isset( $stored[ $console ][ $id ] ) && is_array( $stored[ $console ][ $id ] ) ) {
				$out[ $id ] = array_merge( $binding, $stored[ $console ][ $id ] );
			}
		}
		return $out;
	}

	/**
	 * Value for window.EJS_defaultControls: player 1 gets the binding, other players stay unbound.
	 *
	 * @param string $console Console code.
	 * @return array<int, object>
	 */
	public static function ejs( string $console ): array {
		$player = array();
		foreach ( self::mapping( $console ) as $id => $binding ) {
			$player[ $id ] = array(
				'value'  => $binding['key'],
				'value2' => $binding['pad'],
			);
		}
		return array( (object) $player, (object) array(), (object) array(), (object) array() );
	}

	/**
	 * Sanitizes the controls setting: console => id => {key, pad}.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, array<int, array{key: string, pad: string}>>
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$pads  = self::pad_inputs();
		$out   = array();
		foreach ( array_keys( Youem_Consoles::all() ) as $console ) {
			$defaults = self::defaults( $console );
			if ( ! $defaults ) {
				continue;
			}
			$raw = isset( $input[ $console ] ) && is_array( $input[ $console ] ) ? $input[ $console ] : array();
			foreach ( $defaults as $id => $binding ) {
				$row = isset( $raw[ $id ] ) && is_array( $raw[ $id ] ) ? $raw[ $id ] : $binding;
				$key = strtolower( trim( (string) ( $row['key'] ?? '' ) ) );
				$pad = (string) ( $row['pad'] ?? '' );

				$out[ $console ][ $id ] = array(
					'key' => in_array( $key, self::KEYS, true ) ? $key : '',
					'pad' => isset( $pads[ $pad ] ) ? $pad : '',
				);
			}
		}
		return $out;
	}

	/**
	 * Human-readable keyboard key name.
	 *
	 * @param string $key EmulatorJS key name.
	 */
	public static function key_label( string $key ): string {
		$map = array(
			'up arrow'      => '↑',
			'down arrow'    => '↓',
			'left arrow'    => '←',
			'right arrow'   => '→',
			'enter'         => 'Enter',
			'space'         => 'Space',
			'shift'         => 'Shift',
			'ctrl'          => 'Ctrl',
			'alt'           => 'Alt',
			'tab'           => 'Tab',
			'backspace'     => 'Backspace',
			'escape'        => 'Esc',
			'semi-colon'    => ';',
			'equal sign'    => '=',
			'comma'         => ',',
			'dash'          => '-',
			'period'        => '.',
			'forward slash' => '/',
			'grave accent'  => '`',
			'open bracket'  => '[',
			'back slash'    => '\\',
			'close braket'  => ']',
			'single quote'  => "'",
		);
		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}
		return 1 === strlen( $key ) ? strtoupper( $key ) : ucwords( $key );
	}
}
