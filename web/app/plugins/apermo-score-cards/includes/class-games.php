<?php
/**
 * Game data management for Apermo Score Cards.
 *
 * Games are stored as post meta on the post containing the score card block.
 *
 * @package ApermoScoreCards
 */

declare(strict_types=1);

namespace Apermo\ScoreCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Games class for managing game data via post meta.
 */
class Games {

	/**
	 * Meta key prefix for game data.
	 *
	 * @var string
	 */
	public const META_PREFIX = '_asc_game_';

	/**
	 * Initialize the games component.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Games use post meta, no special init needed.
	}

	/**
	 * Get game data for a specific block on a post.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $block_id Block instance ID.
	 * @return array|null Game data or null if not found.
	 */
	public static function get( int $post_id, string $block_id ): ?array {
		$meta_key = self::META_PREFIX . $block_id;
		$data     = get_post_meta( $post_id, $meta_key, true );

		if ( empty( $data ) ) {
			return null;
		}

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Get all games for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of games keyed by block ID.
	 */
	public static function get_all_for_post( int $post_id ): array {
		global $wpdb;

		$prefix = self::META_PREFIX;
		$games  = array();

		// Get all meta keys that match our prefix.
		$meta_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
				$post_id,
				$wpdb->esc_like( $prefix ) . '%'
			)
		);

		foreach ( $meta_rows as $row ) {
			$block_id = substr( $row->meta_key, strlen( $prefix ) );
			$data     = maybe_unserialize( $row->meta_value );

			if ( is_array( $data ) ) {
				$games[ $block_id ] = $data;
			}
		}

		return $games;
	}

	/**
	 * Save game data for a specific block on a post.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $block_id Block instance ID.
	 * @param array  $data     Game data.
	 * @return bool True on success, false on failure.
	 */
	public static function save( int $post_id, string $block_id, array $data ): bool {
		$meta_key = self::META_PREFIX . $block_id;

		$data = wp_parse_args(
			$data,
			array(
				'blockId'     => $block_id,
				'gameType'    => '',
				'playerIds'   => array(),
				'status'      => 'in_progress',
				'rounds'      => array(),
				'finalScores' => array(),
				'winnerId'    => null,
				'startedAt'   => current_time( 'c' ),
				'completedAt' => null,
			)
		);

		// update_post_meta returns:
		// - meta_id (int) if meta didn't exist and was created
		// - true if meta was updated
		// - false if value is the same OR if update failed
		// We need to distinguish "same value" from "actual failure".
		$result = update_post_meta( $post_id, $meta_key, $data );

		// If result is false, check if it's because the value is already correct.
		if ( false === $result ) {
			$existing = get_post_meta( $post_id, $meta_key, true );
			// If existing data matches what we tried to save, consider it success.
			if ( $existing === $data ) {
				return true;
			}
			// Also check serialized comparison for edge cases with array key types.
			if ( maybe_serialize( $existing ) === maybe_serialize( $data ) ) {
				return true;
			}
			return false;
		}

		return true;
	}

	/**
	 * Delete game data for a specific block on a post.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $block_id Block instance ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( int $post_id, string $block_id ): bool {
		$meta_key = self::META_PREFIX . $block_id;

		return delete_post_meta( $post_id, $meta_key );
	}

	/**
	 * Add a round to a game.
	 *
	 * Creates the game if it doesn't exist yet.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $block_id   Block instance ID.
	 * @param array  $round_data Round data.
	 * @param string $game_type  Game type (used when creating new game).
	 * @return bool True on success, false on failure.
	 */
	public static function add_round( int $post_id, string $block_id, array $round_data, string $game_type = 'wizard' ): bool {
		$game = self::get( $post_id, $block_id );

		// Create game if it doesn't exist.
		if ( ! $game ) {
			// Extract player IDs from round data.
			$player_ids = array();
			foreach ( $round_data as $key => $value ) {
				if ( is_numeric( $key ) ) {
					$player_ids[] = (int) $key;
				}
			}

			$game = array(
				'blockId'     => $block_id,
				'gameType'    => $game_type,
				'playerIds'   => $player_ids,
				'status'      => 'in_progress',
				'rounds'      => array(),
				'finalScores' => array(),
				'winnerId'    => null,
				'startedAt'   => current_time( 'c' ),
				'completedAt' => null,
			);
		}

		$game['rounds'][] = $round_data;

		return self::save( $post_id, $block_id, $game );
	}

	/**
	 * Update a specific round in a game.
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $block_id    Block instance ID.
	 * @param int    $round_index Round index (0-based).
	 * @param array  $round_data  Round data.
	 * @return bool True on success, false on failure.
	 */
	public static function update_round( int $post_id, string $block_id, int $round_index, array $round_data ): bool {
		$game = self::get( $post_id, $block_id );

		if ( ! $game || ! isset( $game['rounds'][ $round_index ] ) ) {
			return false;
		}

		$game['rounds'][ $round_index ] = $round_data;

		return self::save( $post_id, $block_id, $game );
	}

	/**
	 * Complete a game with final scores.
	 *
	 * @param int    $post_id      Post ID.
	 * @param string $block_id     Block instance ID.
	 * @param array  $final_scores Final scores keyed by player ID.
	 * @param int    $winner_id    Winner player ID.
	 * @return bool True on success, false on failure.
	 */
	public static function complete( int $post_id, string $block_id, array $final_scores, int $winner_id ): bool {
		$game = self::get( $post_id, $block_id );

		if ( ! $game ) {
			return false;
		}

		$game['status']      = 'completed';
		$game['finalScores'] = $final_scores;
		$game['winnerId']    = $winner_id;
		$game['completedAt'] = current_time( 'c' );

		return self::save( $post_id, $block_id, $game );
	}
}