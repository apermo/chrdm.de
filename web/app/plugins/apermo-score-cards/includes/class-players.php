<?php
/**
 * Player management for Apermo Score Cards.
 *
 * Players are all WordPress users. Manage users via WordPress admin.
 *
 * @package ApermoScoreCards
 */

declare(strict_types=1);

namespace Apermo\ScoreCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Players class using WordPress users.
 */
class Players {

	/**
	 * Initialize the players component.
	 *
	 * @return void
	 */
	public static function init(): void {
		// All users are players, managed via WP admin.
	}

	/**
	 * Get all available players.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of player data.
	 */
	public static function get_all( array $args = array() ): array {
		$defaults = array(
			'orderby' => 'display_name',
			'order'   => 'ASC',
		);

		$users   = get_users( wp_parse_args( $args, $defaults ) );
		$players = array();

		foreach ( $users as $user ) {
			$players[] = self::format_player( $user );
		}

		return $players;
	}

	/**
	 * Get a player by user ID.
	 *
	 * @param int $user_id User ID.
	 * @return array|null Player data or null.
	 */
	public static function get( int $user_id ): ?array {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return null;
		}

		return self::format_player( $user );
	}

	/**
	 * Get players by user IDs.
	 *
	 * @param array $user_ids Array of user IDs.
	 * @return array Array of player data.
	 */
	public static function get_by_ids( array $user_ids ): array {
		if ( empty( $user_ids ) ) {
			return array();
		}

		$users = get_users(
			array(
				'include' => array_map( 'absint', $user_ids ),
				'orderby' => 'include',
			)
		);

		$players = array();

		foreach ( $users as $user ) {
			$players[] = self::format_player( $user );
		}

		return $players;
	}

	/**
	 * Format a user as player data.
	 *
	 * @param \WP_User $user User object.
	 * @return array Player data.
	 */
	private static function format_player( \WP_User $user ): array {
		return array(
			'id'        => $user->ID,
			'name'      => $user->display_name,
			'avatarUrl' => get_avatar_url( $user->ID, array( 'size' => 96 ) ),
		);
	}
}