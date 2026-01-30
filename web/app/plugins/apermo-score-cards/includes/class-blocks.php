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
	 * Game block types (excluding evening-summary).
	 *
	 * @var array
	 */
	private static array $game_block_types = array(
		'apermo-score-cards/darts',
		'apermo-score-cards/pool',
	);

	/**
	 * Initialize blocks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor_assets' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_frontend_data' ) );
		add_filter( 'the_content', array( self::class, 'append_evening_summary' ), 20 );
	}

	/**
	 * Register all blocks from block.json files.
	 * Blocks are registered for metadata only - scripts come from main bundle.
	 *
	 * @return void
	 */
	public static function register_blocks(): void {
		$blocks_dir = ASC_PLUGIN_DIR . 'src/blocks';

		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		$block_folders = glob( $blocks_dir . '/*', GLOB_ONLYDIR );

		foreach ( $block_folders as $block_folder ) {
			$block_json = $block_folder . '/block.json';

			if ( file_exists( $block_json ) ) {
				register_block_type(
					$block_json,
					array(
						// Override script/style since we bundle everything.
						'editor_script' => 'apermo-score-cards-editor',
						'editor_style'  => 'apermo-score-cards-editor',
						'style'         => 'apermo-score-cards-style',
					)
				);
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

		if ( file_exists( ASC_PLUGIN_DIR . 'build/style-index.css' ) ) {
			wp_enqueue_style(
				'apermo-score-cards-style',
				ASC_PLUGIN_URL . 'build/style-index.css',
				array(),
				$asset['version']
			);
		}

		wp_localize_script(
			'apermo-score-cards-editor',
			'apermoScoreCards',
			array(
				'restUrl'   => rest_url( REST_API::NAMESPACE ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'canManage' => current_user_can( Capabilities::CAPABILITY ),
				'gameTypes' => self::get_registered_game_types(),
			)
		);
	}

	/**
	 * Enqueue frontend data for interactive blocks.
	 *
	 * @return void
	 */
	public static function enqueue_frontend_data(): void {
		global $post;

		if ( ! $post ) {
			return;
		}

		// Check if post content contains any score card blocks.
		$has_scorecard = self::post_has_game_blocks( $post );

		if ( ! $has_scorecard ) {
			return;
		}

		// Check if user can manage - only load frontend JS if they can.
		$can_manage = Capabilities::user_can_manage( $post->ID );

		// Enqueue frontend styles.
		$asset_file = ASC_PLUGIN_DIR . 'build/index.asset.php';
		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;

			if ( file_exists( ASC_PLUGIN_DIR . 'build/style-index.css' ) ) {
				wp_enqueue_style(
					'apermo-score-cards-style',
					ASC_PLUGIN_URL . 'build/style-index.css',
					array(),
					$asset['version']
				);
			}
		}

		// Add data for frontend interactivity.
		$data = array(
			'restUrl'    => rest_url( REST_API::NAMESPACE ),
			'restNonce'  => wp_create_nonce( 'wp_rest' ),
			'canManage'  => $can_manage,
			'isLoggedIn' => is_user_logged_in(),
			'postId'     => $post->ID,
		);

		wp_register_script( 'apermo-score-cards-frontend-data', false, array(), ASC_VERSION, true );
		wp_enqueue_script( 'apermo-score-cards-frontend-data' );
		wp_add_inline_script(
			'apermo-score-cards-frontend-data',
			'window.apermoScoreCards = ' . wp_json_encode( $data ) . ';'
		);

		// Enqueue frontend JS if user can manage scores.
		if ( $can_manage ) {
			$frontend_asset = ASC_PLUGIN_DIR . 'build/frontend.asset.php';
			if ( file_exists( $frontend_asset ) ) {
				$frontend = require $frontend_asset;

				wp_enqueue_script(
					'apermo-score-cards-frontend',
					ASC_PLUGIN_URL . 'build/frontend.js',
					array_merge( $frontend['dependencies'], array( 'apermo-score-cards-frontend-data' ) ),
					$frontend['version'],
					true
				);
			}
		}
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

	/**
	 * Check if a post contains any game blocks.
	 *
	 * @param \WP_Post $post The post to check.
	 * @return bool True if post has game blocks.
	 */
	public static function post_has_game_blocks( \WP_Post $post ): bool {
		foreach ( self::$game_block_types as $block_type ) {
			if ( has_block( $block_type, $post ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Append evening summary to post content if game blocks are present.
	 *
	 * @param string $content The post content.
	 * @return string Modified content with evening summary appended.
	 */
	public static function append_evening_summary( string $content ): string {
		// Only on singular posts/pages in the main query.
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		global $post;

		if ( ! $post || ! self::post_has_game_blocks( $post ) ) {
			return $content;
		}

		// Don't append if evening summary block is already in the content.
		if ( has_block( 'apermo-score-cards/evening-summary', $post ) ) {
			return $content;
		}

		// Render the evening summary block.
		$summary_block = '<!-- wp:apermo-score-cards/evening-summary /-->';
		$rendered      = do_blocks( $summary_block );

		return $content . $rendered;
	}
}
