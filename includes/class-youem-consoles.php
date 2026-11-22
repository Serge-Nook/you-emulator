<?php
/**
 * Registry of supported systems.
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Supported consoles, their EmulatorJS cores, ROM extensions and BIOS rules.
 */
class Youem_Consoles {

	/**
	 * Returns all consoles keyed by EmulatorJS system code.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		$consoles = array(
			'nes'    => array(
				'label'      => 'NES',
				'short'      => 'NES',
				'cores'      => array( 'fceumm', 'nestopia' ),
				'extensions' => array( 'nes', 'fds', 'unf', 'unif', 'zip', '7z' ),
				'bios'       => 'none',
				'bios_files' => array( 'disksys.rom' ),
				'heavy'      => false,
				'threads'    => false,
				'tgdb'       => 7,
				'ss'         => 3,
			),
			'snes'   => array(
				'label'      => 'SNES',
				'short'      => 'SNES',
				'cores'      => array( 'snes9x' ),
				'extensions' => array( 'smc', 'sfc', 'fig', 'swc', 'zip', '7z' ),
				'bios'       => 'none',
				'bios_files' => array(),
				'heavy'      => false,
				'threads'    => false,
				'tgdb'       => 6,
				'ss'         => 4,
			),
			'segaMD' => array(
				'label'      => 'Sega Genesis',
				'short'      => 'Genesis',
				'cores'      => array( 'genesis_plus_gx', 'picodrive' ),
				'extensions' => array( 'md', 'gen', 'smd', 'bin', 'mdx', 'zip', '7z' ),
				'bios'       => 'none',
				'bios_files' => array(),
				'heavy'      => false,
				'threads'    => false,
				'tgdb'       => 18,
				'ss'         => 1,
			),
			'gba'    => array(
				'label'      => 'GBA',
				'short'      => 'GBA',
				'cores'      => array( 'mgba' ),
				'extensions' => array( 'gba', 'agb', 'zip', '7z' ),
				'bios'       => 'optional',
				'bios_files' => array( 'gba_bios.bin' ),
				'heavy'      => false,
				'threads'    => false,
				'tgdb'       => 5,
				'ss'         => 12,
			),
			'psx'    => array(
				'label'      => 'PlayStation',
				'short'      => 'PS1',
				'cores'      => array( 'mednafen_psx_hw', 'pcsx_rearmed' ),
				'extensions' => array( 'cue', 'bin', 'img', 'mdf', 'pbp', 'chd', 'm3u', 'exe', 'zip', '7z' ),
				'bios'       => 'required',
				'hle_cores'  => array( 'pcsx_rearmed' ),
				'bios_files' => array( 'scph5500.bin', 'scph5501.bin', 'scph5502.bin', 'scph1001.bin', 'scph7001.bin', 'scph101.bin' ),
				'heavy'      => false,
				'threads'    => false,
				'tgdb'       => 10,
				'ss'         => 57,
			),
			'psp'    => array(
				'label'      => 'PSP',
				'short'      => 'PSP',
				'cores'      => array( 'ppsspp' ),
				'extensions' => array( 'iso', 'cso', 'pbp', 'chd', 'zip', '7z' ),
				'bios'       => 'optional',
				'bios_files' => array(),
				'heavy'      => true,
				'threads'    => true,
				'tgdb'       => 13,
				'ss'         => 61,
			),
			'n64'    => array(
				'label'      => 'N64',
				'short'      => 'N64',
				'cores'      => array( 'mupen64plus_next', 'parallel_n64' ),
				'extensions' => array( 'z64', 'n64', 'v64', 'ndd', 'zip', '7z' ),
				'bios'       => 'none',
				'bios_files' => array(),
				'heavy'      => true,
				'threads'    => false,
				'tgdb'       => 3,
				'ss'         => 14,
			),
		);

		/**
		 * Filters the supported console registry.
		 *
		 * @param array $consoles Consoles keyed by EmulatorJS system code.
		 */
		return apply_filters( 'youem_consoles', $consoles );
	}

	/**
	 * Whether a system code is supported.
	 *
	 * @param string $code System code.
	 */
	public static function exists( string $code ): bool {
		return isset( self::all()[ $code ] );
	}

	/**
	 * Returns one console definition or null.
	 *
	 * @param string $code System code.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $code ): ?array {
		$all = self::all();
		return $all[ $code ] ?? null;
	}

	/**
	 * Taxonomy term slug for a system code.
	 *
	 * @param string $code System code.
	 */
	public static function term_slug( string $code ): string {
		return strtolower( $code );
	}

	/**
	 * System code for a taxonomy term slug.
	 *
	 * @param string $slug Term slug.
	 */
	public static function code_from_slug( string $slug ): ?string {
		foreach ( array_keys( self::all() ) as $code ) {
			if ( self::term_slug( $code ) === strtolower( $slug ) ) {
				return $code;
			}
		}
		return null;
	}

	/**
	 * Default core for a system.
	 *
	 * @param string $code System code.
	 */
	public static function default_core( string $code ): string {
		$console = self::get( $code );
		return $console ? (string) $console['cores'][0] : '';
	}

	/**
	 * All cores used by the registry.
	 *
	 * @return string[]
	 */
	public static function all_cores(): array {
		$cores = array();
		foreach ( self::all() as $console ) {
			$cores = array_merge( $cores, $console['cores'] );
		}
		return array_values( array_unique( $cores ) );
	}
}
