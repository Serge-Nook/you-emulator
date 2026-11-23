<?php
/**
 * Access control tests.
 *
 * @package YouEmulator
 */

use WP_Mock\Tools\TestCase;

/**
 * Per-game visibility.
 */
class AccessTest extends TestCase {

	private function game( string $status = 'publish', string $password = '' ): WP_Post {
		$p                = new WP_Post();
		$p->ID            = 5;
		$p->post_type     = 'youem_game';
		$p->post_status   = $status;
		$p->post_password = $password;
		return $p;
	}

	private function user( int $id, array $roles, array $caps ): object {
		$u        = new stdClass();
		$u->ID    = $id;
		$u->roles = $roles;
		$u->caps  = $caps;
		return $u;
	}

	private function mock( WP_Post $post, object $user, string $visibility, array $users = array(), array $roles = array() ): void {
		WP_Mock::userFunction( 'get_post', array( 'return' => $post ) );
		WP_Mock::userFunction( 'get_userdata', array( 'return' => $user ) );
		WP_Mock::userFunction( 'user_can', array( 'return' => static fn( $u, $cap ) => in_array( $cap, $u->caps, true ) ) );
		WP_Mock::userFunction(
			'get_post_meta',
			array(
				'return' => static function ( $id, $key ) use ( $visibility, $users, $roles ) {
					return array(
						'_youem_visibility'    => $visibility,
						'_youem_allowed_users' => $users,
						'_youem_allowed_roles' => $roles,
					)[ $key ] ?? '';
				},
			)
		);
	}

	public function test_guest_denied(): void {
		WP_Mock::userFunction( 'get_post', array( 'return' => $this->game() ) );
		$this->assertFalse( Youem_Access::can_access( 5, 0 ) );
	}

	public function test_public_game_for_player(): void {
		$this->mock( $this->game(), $this->user( 2, array( 'subscriber' ), array( 'youem_play' ) ), 'public' );
		$this->assertTrue( Youem_Access::can_access( 5, 2 ) );
	}

	public function test_player_without_cap_denied(): void {
		$this->mock( $this->game(), $this->user( 2, array( 'subscriber' ), array() ), 'public' );
		$this->assertFalse( Youem_Access::can_access( 5, 2 ) );
	}

	public function test_private_by_user(): void {
		$this->mock( $this->game(), $this->user( 2, array( 'subscriber' ), array( 'youem_play' ) ), 'private', array( '2' ) );
		$this->assertTrue( Youem_Access::can_access( 5, 2 ) );
		$this->mock( $this->game(), $this->user( 3, array( 'subscriber' ), array( 'youem_play' ) ), 'private', array( '2' ) );
		$this->assertFalse( Youem_Access::can_access( 5, 3 ) );
	}

	public function test_private_by_role(): void {
		$this->mock( $this->game(), $this->user( 3, array( 'author' ), array( 'youem_play' ) ), 'private', array(), array( 'author' ) );
		$this->assertTrue( Youem_Access::can_access( 5, 3 ) );
	}

	public function test_draft_and_password_hidden(): void {
		$this->mock( $this->game( 'draft' ), $this->user( 2, array(), array( 'youem_play' ) ), 'public' );
		$this->assertFalse( Youem_Access::can_access( 5, 2 ) );
		$this->mock( $this->game( 'publish', 'secret' ), $this->user( 2, array(), array( 'youem_play' ) ), 'public' );
		$this->assertFalse( Youem_Access::can_access( 5, 2 ) );
	}

	public function test_manager_sees_everything(): void {
		$this->mock( $this->game( 'draft' ), $this->user( 1, array( 'administrator' ), array( 'youem_manage_games' ) ), 'private' );
		$this->assertTrue( Youem_Access::can_access( 5, 1 ) );
	}

	public function test_meta_cap(): void {
		$this->mock( $this->game(), $this->user( 3, array(), array( 'youem_play' ) ), 'private' );
		$this->assertSame( array( 'do_not_allow' ), Youem_Access::map_meta_cap( array(), 'youem_play_game', 3, array( 5 ) ) );
		$this->assertSame( array( 'edit_posts' ), Youem_Access::map_meta_cap( array( 'edit_posts' ), 'edit_posts', 3, array() ) );
	}

	public function test_query_args_guest(): void {
		WP_Mock::userFunction( 'get_userdata', array( 'return' => false ) );
		$this->assertSame( array( 'post__in' => array( 0 ) ), Youem_Access::query_args( 0 ) );
	}
}
