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
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor_assets' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_frontend_assets' ) );
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
	 * Enqueue editor assets.
	 *
	 * @return void
	 */
	public static function enqueue_editor_assets(): void {
		$asset_file = ASC_PLUGIN_DIR . 'build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'apermo-score-cards-editor',
			ASC_PLUGIN_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( file_exists( ASC_PLUGIN_DIR . 'build/index.css' ) ) {
			wp_enqueue_style(
				'apermo-score-cards-editor',
				ASC_PLUGIN_URL . 'build/index.css',
				array( 'wp-components' ),
				$asset['version']
			);
		}

		wp_localize_script(
			'apermo-score-cards-editor',
			'apermoScoreCards',
			array(
				'restUrl'      => rest_url( REST_API::NAMESPACE ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'canManage'    => current_user_can( Capabilities::CAPABILITY ),
				'gameTypes'    => self::get_registered_game_types(),
			)
		);
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	public static function enqueue_frontend_assets(): void {
		$asset_file = ASC_PLUGIN_DIR . 'build/frontend.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'apermo-score-cards-frontend',
			ASC_PLUGIN_URL . 'build/frontend.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'apermo-score-cards-frontend',
			'apermoScoreCards',
			array(
				'restUrl'      => rest_url( REST_API::NAMESPACE ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'canManage'    => current_user_can( Capabilities::CAPABILITY ),
				'isLoggedIn'   => is_user_logged_in(),
			)
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