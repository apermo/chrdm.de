<?php
/**
 * REST API endpoints for Apermo Score Cards.
 *
 * @package ApermoScoreCards
 */

declare(strict_types=1);

namespace Apermo\ScoreCards;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API class for handling API requests.
 */
class REST_API {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	public const NAMESPACE = 'apermo-score-cards/v1';

	/**
	 * Initialize the REST API.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		// Players endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/players',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_players' ),
				'permission_callback' => '__return_true',
			)
		);

		// Game data endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<post_id>\d+)/games/(?P<block_id>[a-zA-Z0-9-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_game' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'save_game' ),
					'permission_callback' => array( self::class, 'can_manage_scorecard' ),
					'args'                => self::get_game_args(),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'save_game' ),
					'permission_callback' => array( self::class, 'can_manage_scorecard' ),
					'args'                => self::get_game_args(),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( self::class, 'delete_game' ),
					'permission_callback' => array( self::class, 'can_manage_scorecard' ),
				),
			)
		);

		// Round endpoints.
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<post_id>\d+)/games/(?P<block_id>[a-zA-Z0-9-]+)/rounds',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'add_round' ),
				'permission_callback' => array( self::class, 'can_manage_scorecard' ),
				'args'                => array(
					'roundData' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<post_id>\d+)/games/(?P<block_id>[a-zA-Z0-9-]+)/rounds/(?P<round_index>\d+)',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( self::class, 'update_round' ),
				'permission_callback' => array( self::class, 'can_manage_scorecard' ),
				'args'                => array(
					'roundData' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);

		// Complete game endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<post_id>\d+)/games/(?P<block_id>[a-zA-Z0-9-]+)/complete',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'complete_game' ),
				'permission_callback' => array( self::class, 'can_manage_scorecard' ),
				'args'                => array(
					'finalScores' => array(
						'type'     => 'object',
						'required' => true,
					),
					'winnerId'    => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Permission check endpoint for frontend.
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<post_id>\d+)/can-manage',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'check_can_manage' ),
				'permission_callback' => '__return_true',
			)
		);

		// Duplicate block endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<post_id>\d+)/duplicate-block/(?P<block_id>[a-zA-Z0-9-]+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'duplicate_block' ),
				'permission_callback' => array( self::class, 'can_manage_scorecard' ),
			)
		);

		// Update block players endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<post_id>\d+)/blocks/(?P<block_id>[a-zA-Z0-9-]+)/players',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'update_block_players' ),
				'permission_callback' => array( self::class, 'can_manage_scorecard' ),
				'args'                => array(
					'playerIds' => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'integer' ),
					),
				),
			)
		);
	}

	/**
	 * Check if user can manage the score card.
	 * Uses custom capability with 8-hour time window.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return bool|WP_Error True if user can manage, WP_Error otherwise.
	 */
	public static function can_manage_scorecard( WP_REST_Request $request ): bool|WP_Error {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( ! Capabilities::user_can_manage( $post_id ) ) {
			// Check if it's a capability issue or time window issue.
			if ( ! current_user_can( Capabilities::CAPABILITY ) ) {
				return new WP_Error(
					'rest_forbidden',
					__( 'You do not have permission to manage score cards.', 'apermo-score-cards' ),
					array( 'status' => 403 )
				);
			}

			// Time window has passed.
			return new WP_Error(
				'rest_forbidden_time',
				__( 'The edit window for this score card has closed (8 hours after last post update).', 'apermo-score-cards' ),
				array(
					'status'        => 403,
					'post_modified' => get_post_modified_time( 'c', true, $post_id ),
				)
			);
		}

		return true;
	}

	/**
	 * Check if current user can manage a post's scorecards.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function check_can_manage( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );

		$can_manage      = Capabilities::user_can_manage( $post_id );
		$has_capability  = current_user_can( Capabilities::CAPABILITY );
		$within_window   = Capabilities::is_within_edit_window( $post_id );
		$remaining_time  = Capabilities::get_remaining_edit_time( $post_id );
		$remaining_human = Capabilities::get_remaining_edit_time_human( $post_id );

		return new WP_REST_Response(
			array(
				'canManage'          => $can_manage,
				'hasCapability'      => $has_capability,
				'withinEditWindow'   => $within_window,
				'remainingSeconds'   => $remaining_time,
				'remainingHuman'     => $remaining_human,
				'editWindowHours'    => Capabilities::EDIT_WINDOW_SECONDS / HOUR_IN_SECONDS,
			),
			200
		);
	}

	/**
	 * Get game endpoint arguments.
	 *
	 * @return array Endpoint arguments.
	 */
	private static function get_game_args(): array {
		return array(
			'gameType'      => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'playerIds'     => array(
				'type'     => 'array',
				'required' => true,
				'items'    => array( 'type' => 'integer' ),
			),
			'status'        => array(
				'type'              => 'string',
				'enum'              => array( 'in_progress', 'completed', 'cancelled' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'scores'        => array(
				'type' => 'object',
			),
			'finalScores'   => array(
				'type' => 'object',
			),
			'positions'     => array(
				'type' => 'object',
			),
			'finishedRound' => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'winnerId'      => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'winnerIds'     => array(
				'type'  => 'array',
				'items' => array( 'type' => 'integer' ),
			),
			'games'         => array(
				'type'  => 'array',
				'items' => array( 'type' => 'object' ),
			),
		);
	}

	/**
	 * Get all players.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public static function get_players( WP_REST_Request $request ): WP_REST_Response {
		$players = Players::get_all();

		return new WP_REST_Response( $players, 200 );
	}

	/**
	 * Get game data.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public static function get_game( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id  = (int) $request->get_param( 'post_id' );
		$block_id = sanitize_text_field( $request->get_param( 'block_id' ) );

		$game = Games::get( $post_id, $block_id );

		if ( ! $game ) {
			return new WP_Error(
				'not_found',
				__( 'Game not found.', 'apermo-score-cards' ),
				array( 'status' => 404 )
			);
		}

		return new WP_REST_Response( $game, 200 );
	}

	/**
	 * Save game data.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public static function save_game( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id  = (int) $request->get_param( 'post_id' );
		$block_id = sanitize_text_field( $request->get_param( 'block_id' ) );

		$data = array(
			'gameType'      => $request->get_param( 'gameType' ),
			'playerIds'     => $request->get_param( 'playerIds' ),
			'status'        => $request->get_param( 'status' ) ?? 'in_progress',
			'scores'        => $request->get_param( 'scores' ) ?? array(),
			'finalScores'   => $request->get_param( 'finalScores' ) ?? array(),
			'positions'     => $request->get_param( 'positions' ) ?? array(),
			'finishedRound' => $request->get_param( 'finishedRound' ),
			'winnerId'      => $request->get_param( 'winnerId' ),
			'winnerIds'     => $request->get_param( 'winnerIds' ) ?? array(),
			'games'         => $request->get_param( 'games' ) ?? array(),
		);

		$result = Games::save( $post_id, $block_id, $data );

		if ( ! $result ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to save game.', 'apermo-score-cards' ),
				array( 'status' => 500 )
			);
		}

		$game = Games::get( $post_id, $block_id );

		return new WP_REST_Response( $game, 200 );
	}

	/**
	 * Delete game data.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public static function delete_game( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id  = (int) $request->get_param( 'post_id' );
		$block_id = sanitize_text_field( $request->get_param( 'block_id' ) );

		$result = Games::delete( $post_id, $block_id );

		if ( ! $result ) {
			return new WP_Error(
				'delete_failed',
				__( 'Failed to delete game.', 'apermo-score-cards' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Add a round to a game.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public static function add_round( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id    = (int) $request->get_param( 'post_id' );
		$block_id   = sanitize_text_field( $request->get_param( 'block_id' ) );
		$round_data = $request->get_param( 'roundData' );

		$result = Games::add_round( $post_id, $block_id, $round_data );

		if ( ! $result ) {
			return new WP_Error(
				'add_round_failed',
				__( 'Failed to add round.', 'apermo-score-cards' ),
				array( 'status' => 500 )
			);
		}

		$game = Games::get( $post_id, $block_id );

		return new WP_REST_Response( $game, 200 );
	}

	/**
	 * Update a round in a game.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public static function update_round( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id     = (int) $request->get_param( 'post_id' );
		$block_id    = sanitize_text_field( $request->get_param( 'block_id' ) );
		$round_index = (int) $request->get_param( 'round_index' );
		$round_data  = $request->get_param( 'roundData' );

		$result = Games::update_round( $post_id, $block_id, $round_index, $round_data );

		if ( ! $result ) {
			return new WP_Error(
				'update_round_failed',
				__( 'Failed to update round.', 'apermo-score-cards' ),
				array( 'status' => 500 )
			);
		}

		$game = Games::get( $post_id, $block_id );

		return new WP_REST_Response( $game, 200 );
	}

	/**
	 * Complete a game.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public static function complete_game( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id      = (int) $request->get_param( 'post_id' );
		$block_id     = sanitize_text_field( $request->get_param( 'block_id' ) );
		$final_scores = $request->get_param( 'finalScores' );
		$winner_id    = (int) $request->get_param( 'winnerId' );

		$result = Games::complete( $post_id, $block_id, $final_scores, $winner_id );

		if ( ! $result ) {
			return new WP_Error(
				'complete_failed',
				__( 'Failed to complete game.', 'apermo-score-cards' ),
				array( 'status' => 500 )
			);
		}

		$game = Games::get( $post_id, $block_id );

		return new WP_REST_Response( $game, 200 );
	}

	/**
	 * Duplicate a score card block in a post.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public static function duplicate_block( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id  = (int) $request->get_param( 'post_id' );
		$block_id = sanitize_text_field( $request->get_param( 'block_id' ) );

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'post_not_found',
				__( 'Post not found.', 'apermo-score-cards' ),
				array( 'status' => 404 )
			);
		}

		// Parse the post content into blocks.
		$blocks = parse_blocks( $post->post_content );

		// Find the source block and create a duplicate to append at the end.
		$new_block_id  = wp_generate_uuid4();
		$source_block  = null;

		foreach ( $blocks as $block ) {
			if ( self::is_target_block( $block, $block_id ) ) {
				$source_block = $block;
				break;
			}
		}

		if ( ! $source_block ) {
			return new WP_Error(
				'block_not_found',
				__( 'Block not found in post.', 'apermo-score-cards' ),
				array( 'status' => 404 )
			);
		}

		// Create a duplicate with new block ID and append to end.
		$duplicate                     = $source_block;
		$duplicate['attrs']['blockId'] = $new_block_id;
		$duplicate['innerHTML']        = '';
		$duplicate['innerContent']     = array();

		// Remove customTitle from duplicate so it gets auto-generated title.
		unset( $duplicate['attrs']['customTitle'] );

		$blocks[] = $duplicate;

		// Serialize blocks back to content.
		$new_content = serialize_blocks( $blocks );

		// Update the post.
		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $new_content,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success'    => true,
				'newBlockId' => $new_block_id,
			),
			200
		);
	}

	/**
	 * Update players for a block.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public static function update_block_players( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id    = (int) $request->get_param( 'post_id' );
		$block_id   = sanitize_text_field( $request->get_param( 'block_id' ) );
		$player_ids = $request->get_param( 'playerIds' );

		// Check if game has results - don't allow changing players if completed.
		$game = Games::get( $post_id, $block_id );
		if ( $game && 'completed' === ( $game['status'] ?? '' ) ) {
			return new WP_Error(
				'game_completed',
				__( 'Cannot change players after game has results.', 'apermo-score-cards' ),
				array( 'status' => 400 )
			);
		}

		// If game has started (has games), validate that players with games are not being removed.
		$games_list = $game['games'] ?? array();
		if ( ! empty( $games_list ) ) {
			$players_with_games = array();
			foreach ( $games_list as $g ) {
				$players_with_games[ $g['player1'] ?? 0 ] = true;
				$players_with_games[ $g['player2'] ?? 0 ] = true;
			}

			foreach ( array_keys( $players_with_games ) as $player_id ) {
				if ( ! in_array( $player_id, $player_ids, true ) ) {
					return new WP_Error(
						'cannot_remove_active_player',
						__( 'Cannot remove players who have already played games.', 'apermo-score-cards' ),
						array( 'status' => 400 )
					);
				}
			}
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'post_not_found',
				__( 'Post not found.', 'apermo-score-cards' ),
				array( 'status' => 404 )
			);
		}

		// Parse the post content into blocks.
		$blocks = parse_blocks( $post->post_content );

		// Update the block's playerIds attribute.
		$found      = false;
		$new_blocks = self::update_block_attribute( $blocks, $block_id, 'playerIds', $player_ids, $found );

		if ( ! $found ) {
			return new WP_Error(
				'block_not_found',
				__( 'Block not found in post.', 'apermo-score-cards' ),
				array( 'status' => 404 )
			);
		}

		// Serialize blocks back to content.
		$new_content = serialize_blocks( $new_blocks );

		// Update the post.
		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $new_content,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success'   => true,
				'playerIds' => $player_ids,
			),
			200
		);
	}

	/**
	 * Recursively update a block attribute.
	 *
	 * @param array  $blocks    Array of blocks.
	 * @param string $block_id  Target block ID.
	 * @param string $attr_name Attribute name to update.
	 * @param mixed  $value     New attribute value.
	 * @param bool   $found     Reference to found flag.
	 * @return array Updated blocks.
	 */
	private static function update_block_attribute( array $blocks, string $block_id, string $attr_name, $value, bool &$found ): array {
		foreach ( $blocks as &$block ) {
			if ( isset( $block['attrs']['blockId'] ) && $block['attrs']['blockId'] === $block_id ) {
				$block['attrs'][ $attr_name ] = $value;
				// Clear innerHTML/innerContent to force re-render.
				$block['innerHTML']    = '';
				$block['innerContent'] = array();
				$found                 = true;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::update_block_attribute( $block['innerBlocks'], $block_id, $attr_name, $value, $found );
			}
		}

		return $blocks;
	}

	/**
	 * Check if a block matches the target block ID.
	 *
	 * @param array  $block    Block data.
	 * @param string $block_id Target block ID.
	 * @return bool True if block matches.
	 */
	private static function is_target_block( array $block, string $block_id ): bool {
		// Check if this block has the matching blockId attribute.
		if ( isset( $block['attrs']['blockId'] ) && $block['attrs']['blockId'] === $block_id ) {
			return true;
		}

		// Check inner blocks recursively.
		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner_block ) {
				if ( self::is_target_block( $inner_block, $block_id ) ) {
					return true;
				}
			}
		}

		return false;
	}
}