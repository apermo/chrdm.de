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
}