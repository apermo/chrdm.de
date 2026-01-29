<?php
/**
 * Block registration for Apermo Score Cards.
 *
 * @package ApermoScoreCards
 */

declare(strict_types=1);

namespace Apermo\ScoreCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blocks class for registering Gutenberg blocks.
 */
class Blocks {

	/**
	 * Initialize blocks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor_data' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_frontend_data' ) );
	}

	/**
	 * Register all blocks.
	 *
	 * @return void
	 */
	public static function register_blocks(): void {
		$blocks_dir = ASC_PLUGIN_DIR . 'build/blocks';

		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		$block_folders = glob( $blocks_dir . '/*', GLOB_ONLYDIR );

		foreach ( $block_folders as $block_folder ) {
			$block_json = $block_folder . '/block.json';

			if ( file_exists( $block_json ) ) {
				register_block_type( $block_folder );
			}
		}
	}

	/**
	 * Enqueue editor data as inline script.
	 * This provides REST API info to all score card blocks.
	 *
	 * @return void
	 */
	public static function enqueue_editor_data(): void {
		$data = array(
			'restUrl'   => rest_url( REST_API::NAMESPACE ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'canManage' => current_user_can( Capabilities::CAPABILITY ),
			'gameTypes' => self::get_registered_game_types(),
		);

		wp_add_inline_script(
			'wp-blocks',
			'window.apermoScoreCards = ' . wp_json_encode( $data ) . ';',
			'before'
		);
	}

	/**
	 * Enqueue frontend data for interactive blocks.
	 *
	 * @return void
	 */
	public static function enqueue_frontend_data(): void {
		global $post;

		// Check if post content contains any score card blocks.
		if ( ! $post || ! has_block( 'apermo-score-cards/darts', $post ) ) {
			return;
		}

		$data = array(
			'restUrl'    => rest_url( REST_API::NAMESPACE ),
			'restNonce'  => wp_create_nonce( 'wp_rest' ),
			'canManage'  => current_user_can( Capabilities::CAPABILITY ),
			'isLoggedIn' => is_user_logged_in(),
			'postId'     => $post->ID,
		);

		// Add data as a script tag for frontend interactivity.
		wp_register_script( 'apermo-score-cards-frontend-data', false, array(), ASC_VERSION, true );
		wp_enqueue_script( 'apermo-score-cards-frontend-data' );
		wp_add_inline_script(
			'apermo-score-cards-frontend-data',
			'window.apermoScoreCards = ' . wp_json_encode( $data ) . ';',
			'before'
		);
	}

	/**
	 * Get registered game types.
	 *
	 * @return array Array of game type configurations.
	 */
	public static function get_registered_game_types(): array {
		/**
		 * Filter to register game types.
		 *
		 * @param array $game_types Array of game type configurations.
		 */
		return apply_filters( 'apermo_score_cards_game_types', array() );
	}

	/**
	 * Register a game type.
	 *
	 * @param string $slug   Game type slug.
	 * @param array  $config Game type configuration.
	 * @return void
	 */
	public static function register_game_type( string $slug, array $config ): void {
		add_filter(
			'apermo_score_cards_game_types',
			function ( array $game_types ) use ( $slug, $config ): array {
				$game_types[ $slug ] = wp_parse_args(
					$config,
					array(
						'name'        => $slug,
						'description' => '',
						'minPlayers'  => 2,
						'maxPlayers'  => 10,
						'icon'        => 'games',
					)
				);

				return $game_types;
			}
		);
	}
}
