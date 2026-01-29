<?php
/**
 * Block Bindings API registration for Apermo Score Cards.
 *
 * @package ApermoScoreCards
 */

declare(strict_types=1);

namespace Apermo\ScoreCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block Bindings class for registering custom block bindings.
 */
class Block_Bindings {

	/**
	 * Binding source name.
	 *
	 * @var string
	 */
	public const SOURCE_NAME = 'apermo-score-cards/game-data';

	/**
	 * Initialize block bindings.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register_block_bindings' ) );
	}

	/**
	 * Register block binding sources.
	 *
	 * @return void
	 */
	public static function register_block_bindings(): void {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}

		register_block_bindings_source(
			self::SOURCE_NAME,
			array(
				'label'              => __( 'Game Data', 'apermo-score-cards' ),
				'get_value_callback' => array( self::class, 'get_binding_value' ),
				'uses_context'       => array( 'postId', 'apermo-score-cards/blockId' ),
			)
		);
	}

	/**
	 * Get the value for a block binding.
	 *
	 * @param array     $source_args    Array of source arguments.
	 * @param \WP_Block $block_instance The block instance.
	 * @param string    $attribute_name The attribute name.
	 * @return mixed The binding value.
	 */
	public static function get_binding_value( array $source_args, \WP_Block $block_instance, string $attribute_name ): mixed {
		$key      = $source_args['key'] ?? '';
		$post_id  = $block_instance->context['postId'] ?? get_the_ID();
		$block_id = $block_instance->context['apermo-score-cards/blockId'] ?? null;

		if ( ! $post_id || ! $block_id || ! $key ) {
			return null;
		}

		$game = Games::get( (int) $post_id, $block_id );

		if ( ! $game ) {
			return null;
		}

		// Handle nested keys (e.g., 'finalScores.1').
		$keys  = explode( '.', $key );
		$value = $game;

		foreach ( $keys as $k ) {
			if ( is_array( $value ) && isset( $value[ $k ] ) ) {
				$value = $value[ $k ];
			} else {
				return null;
			}
		}

		return $value;
	}

	/**
	 * Get game data for a specific block.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $block_id Block instance ID.
	 * @return array|null Game data or null if not found.
	 */
	public static function get_game_data( int $post_id, string $block_id ): ?array {
		return Games::get( $post_id, $block_id );
	}

	/**
	 * Get players for a game.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $block_id Block instance ID.
	 * @return array Array of player data.
	 */
	public static function get_game_players( int $post_id, string $block_id ): array {
		$game = Games::get( $post_id, $block_id );

		if ( ! $game || empty( $game['playerIds'] ) ) {
			return array();
		}

		return Players::get_by_ids( $game['playerIds'] );
	}
}