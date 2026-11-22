<?php
/**
 * Cover art and metadata from TheGamesDB / ScreenScraper.
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Looks up game metadata and sideloads the box art.
 */
class Youem_Covers {

	/**
	 * Looks up a game.
	 *
	 * @param string $title   Title.
	 * @param string $console System code.
	 * @param string $rom     Optional ROM file name.
	 * @param int    $size    Optional ROM size.
	 * @return array{title:string,year:string,developer:string,players:string,genres:string[],description:string,cover:string}|WP_Error
	 */
	public static function lookup( string $title, string $console, string $rom = '', int $size = 0 ) {
		$def = Youem_Consoles::get( $console );
		if ( ! $def ) {
			return new WP_Error( 'youem_bad_console', __( 'Choose a console first.', 'you-emulator' ) );
		}
		$provider = (string) Youem_Settings::get( 'cover_provider' );
		if ( 'thegamesdb' === $provider ) {
			return self::thegamesdb( $title, (int) $def['tgdb'] );
		}
		if ( 'screenscraper' === $provider ) {
			return self::screenscraper( $title, (int) $def['ss'], $rom, $size );
		}
		return new WP_Error( 'youem_no_provider', __( 'Select a metadata provider and enter its API key in Settings.', 'you-emulator' ) );
	}

	/**
	 * GET JSON.
	 *
	 * @param string $url URL.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function get_json( string $url ) {
		$res = wp_remote_get(
			$url,
			array(
				'timeout'    => 20,
				'user-agent' => 'YouEmulator/' . YOUEM_VERSION . '; ' . home_url(),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code >= 400 || ! is_array( $body ) ) {
			/* translators: %d: HTTP status code. */
			return new WP_Error( 'youem_provider_http', sprintf( __( 'Metadata provider returned HTTP %d.', 'you-emulator' ), $code ) );
		}
		return $body;
	}

	/**
	 * TheGamesDB v1.
	 *
	 * @param string $title    Title.
	 * @param int    $platform Platform ID.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function thegamesdb( string $title, int $platform ) {
		$key = (string) Youem_Settings::get( 'tgdb_key' );
		if ( '' === $key ) {
			return new WP_Error( 'youem_no_key', __( 'Enter the TheGamesDB API key in Settings.', 'you-emulator' ) );
		}
		$url  = add_query_arg(
			array(
				'apikey'           => $key,
				'name'             => $title,
				'fields'           => 'players,genres,overview,developers',
				'include'          => 'boxart',
				'filter[platform]' => $platform,
			),
			'https://api.thegamesdb.net/v1.1/Games/ByGameName'
		);
		$data = self::get_json( $url );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$game = $data['data']['games'][0] ?? null;
		if ( ! is_array( $game ) ) {
			return new WP_Error( 'youem_not_found', __( 'Nothing found in the database.', 'you-emulator' ) );
		}
		$cover = '';
		$base  = (string) ( $data['include']['boxart']['base_url']['large'] ?? $data['include']['boxart']['base_url']['original'] ?? '' );
		foreach ( (array) ( $data['include']['boxart']['data'][ (string) $game['id'] ] ?? array() ) as $art ) {
			if ( 'boxart' === ( $art['type'] ?? '' ) && 'front' === ( $art['side'] ?? '' ) ) {
				$cover = $base . $art['filename'];
				break;
			}
		}
		$genres = array();
		if ( ! empty( $game['genres'] ) ) {
			$map = self::tgdb_lookup( 'Genres', 'genres', $key );
			foreach ( (array) $game['genres'] as $gid ) {
				if ( isset( $map[ $gid ] ) ) {
					$genres[] = $map[ $gid ];
				}
			}
		}
		$developer = '';
		if ( ! empty( $game['developers'][0] ) ) {
			$map       = self::tgdb_lookup( 'Developers', 'developers', $key );
			$developer = (string) ( $map[ $game['developers'][0] ] ?? '' );
		}
		return array(
			'title'       => (string) ( $game['game_title'] ?? $title ),
			'year'        => substr( (string) ( $game['release_date'] ?? '' ), 0, 4 ),
			'developer'   => $developer,
			'players'     => (string) ( $game['players'] ?? '' ),
			'genres'      => $genres,
			'description' => (string) ( $game['overview'] ?? '' ),
			'cover'       => $cover,
		);
	}

	/**
	 * Cached TheGamesDB id => name map.
	 *
	 * @param string $endpoint Endpoint name.
	 * @param string $field    Data field.
	 * @param string $key      API key.
	 * @return array<int|string, string>
	 */
	private static function tgdb_lookup( string $endpoint, string $field, string $key ): array {
		$cache = get_transient( 'youem_tgdb_' . $field );
		if ( is_array( $cache ) ) {
			return $cache;
		}
		$data = self::get_json( add_query_arg( 'apikey', $key, 'https://api.thegamesdb.net/v1/' . $endpoint ) );
		$map  = array();
		if ( ! is_wp_error( $data ) ) {
			foreach ( (array) ( $data['data'][ $field ] ?? array() ) as $id => $row ) {
				$map[ $id ] = (string) ( $row['name'] ?? '' );
			}
			set_transient( 'youem_tgdb_' . $field, $map, WEEK_IN_SECONDS );
		}
		return $map;
	}

	/**
	 * ScreenScraper API v2.
	 *
	 * @param string $title  Title.
	 * @param int    $system System ID.
	 * @param string $rom    ROM file name.
	 * @param int    $size   ROM size.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function screenscraper( string $title, int $system, string $rom, int $size ) {
		$s = Youem_Settings::all();
		if ( '' === $s['ss_devid'] || '' === $s['ss_devpassword'] ) {
			return new WP_Error( 'youem_no_key', __( 'Enter ScreenScraper developer credentials in Settings.', 'you-emulator' ) );
		}
		$auth = array(
			'devid'       => $s['ss_devid'],
			'devpassword' => $s['ss_devpassword'],
			'softname'    => 'YouEmulator',
			'output'      => 'json',
			'ssid'        => $s['ss_user'],
			'sspassword'  => $s['ss_password'],
			'systemeid'   => $system,
		);
		$game = null;
		if ( '' !== $rom ) {
			$data = self::get_json(
				add_query_arg(
					$auth + array(
						'romnom'    => $rom,
						'romtaille' => $size,
					),
					'https://api.screenscraper.fr/api2/jeuInfos.php'
				)
			);
			$game = is_wp_error( $data ) ? null : ( $data['response']['jeu'] ?? null );
		}
		if ( ! is_array( $game ) ) {
			$data = self::get_json( add_query_arg( $auth + array( 'recherche' => $title ), 'https://api.screenscraper.fr/api2/jeuRecherche.php' ) );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			$game = $data['response']['jeux'][0] ?? null;
		}
		if ( ! is_array( $game ) || empty( $game['id'] ) ) {
			return new WP_Error( 'youem_not_found', __( 'Nothing found in the database.', 'you-emulator' ) );
		}
		$lang  = substr( determine_locale(), 0, 2 );
		$pick  = static function ( array $items, string $key = 'text' ) use ( $lang ) {
			foreach ( array( $lang, 'en', 'fr' ) as $want ) {
				foreach ( $items as $item ) {
					if ( ( $item['langue'] ?? $item['region'] ?? '' ) === $want ) {
						return (string) ( $item[ $key ] ?? '' );
					}
				}
			}
			return (string) ( $items[0][ $key ] ?? '' );
		};
		$cover = '';
		foreach ( array( 'box-2D', 'box-3D', 'ss' ) as $type ) {
			foreach ( (array) ( $game['medias'] ?? array() ) as $media ) {
				if ( ( $media['type'] ?? '' ) === $type ) {
					$cover = (string) $media['url'];
					break 2;
				}
			}
		}
		$genres = array();
		foreach ( (array) ( $game['genres'] ?? array() ) as $genre ) {
			$genres[] = $pick( (array) ( $genre['noms'] ?? array() ) );
		}
		$names = array();
		foreach ( (array) ( $game['noms'] ?? array() ) as $n ) {
			$names[] = array(
				'langue' => $n['region'] ?? '',
				'text'   => $n['text'] ?? '',
			);
		}
		return array(
			'title'       => $pick( $names ) ? $pick( $names ) : $title,
			'year'        => substr( $pick( (array) ( $game['dates'] ?? array() ) ), 0, 4 ),
			'developer'   => (string) ( $game['developpeur']['text'] ?? '' ),
			'players'     => (string) ( $game['joueurs']['text'] ?? '' ),
			'genres'      => array_values( array_filter( $genres ) ),
			'description' => $pick( (array) ( $game['synopsis'] ?? array() ) ),
			'cover'       => $cover,
		);
	}

	/**
	 * Applies looked-up data to a game. Manual covers and filled fields win.
	 *
	 * @param int                  $game_id Game ID.
	 * @param array<string, mixed> $info    Lookup result.
	 * @return array<string, mixed> What was applied.
	 */
	public static function apply( int $game_id, array $info ): array {
		$applied = array();
		foreach ( array( 'year', 'developer', 'players' ) as $field ) {
			if ( '' !== (string) $info[ $field ] && '' === (string) get_post_meta( $game_id, '_youem_' . $field, true ) ) {
				update_post_meta( $game_id, '_youem_' . $field, sanitize_text_field( (string) $info[ $field ] ) );
				$applied[ $field ] = $info[ $field ];
			}
		}
		$post = get_post( $game_id );
		if ( $post && '' === trim( $post->post_content ) && '' !== (string) $info['description'] ) {
			wp_update_post(
				array(
					'ID'           => $game_id,
					'post_content' => wp_kses_post( (string) $info['description'] ),
				)
			);
			$applied['description'] = true;
		}
		$term_ids = array();
		foreach ( (array) $info['genres'] as $genre ) {
			$term = get_term_by( 'name', (string) $genre, Youem_Game::TAX_GENRE );
			if ( ! $term ) {
				$res = wp_insert_term( (string) $genre, Youem_Game::TAX_GENRE, array( 'slug' => Youem_Game::unicode_slug( (string) $genre ) ) );
				if ( is_wp_error( $res ) ) {
					continue;
				}
				$term_ids[] = (int) $res['term_id'];
			} else {
				$term_ids[] = (int) $term->term_id;
			}
		}
		if ( $term_ids ) {
			wp_set_object_terms( $game_id, $term_ids, Youem_Game::TAX_GENRE, true );
			$applied['genres'] = $term_ids;
		}
		$manual = get_post_meta( $game_id, '_youem_cover_id', true ) && 'manual' === get_post_meta( $game_id, '_youem_cover_source', true );
		if ( ! $manual && '' !== (string) $info['cover'] ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$id = media_sideload_image( (string) $info['cover'], $game_id, (string) $info['title'], 'id' );
			if ( ! is_wp_error( $id ) ) {
				update_post_meta( $game_id, '_youem_cover_id', (int) $id );
				update_post_meta( $game_id, '_youem_cover_source', 'scraped' );
				$applied['cover'] = (int) $id;
			}
		}
		return $applied;
	}
}
