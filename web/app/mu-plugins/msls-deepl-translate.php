<?php
/**
 * Plugin Name: MSLS DeepL Translate
 * Description: Translates title and content via DeepL when using MSLS quick-create.
 */

namespace Apermo\MslsDeepl;

use lloc\Msls\MslsBlogCollection;
use lloc\Msls\MslsRestApi;
use Throwable;
use WP_Post;

add_action( 'rest_api_init', [ Translator::class, 'register' ], 99 );

/**
 * Hooks MSLS quick-create into DeepL and preserves blocks that must not be translated.
 */
class Translator {

	/**
	 * Gutenberg block types whose content must not be translated.
	 *
	 * Code blocks fail Gutenberg's block validation when DeepL alters the
	 * inner HTML so it no longer matches the stored attributes.
	 */
	private const PRESERVED_BLOCK_TYPES = [
		'wp:kevinbatdorf/code-block-pro',
	];

	/**
	 * Replaces the default "From xx:" prefix with DeepL translation.
	 */
	public static function register(): void {
		remove_filter( 'msls_quick_create_post_data', [ MslsRestApi::class, 'prefix_source_language' ] );
		add_filter( 'msls_quick_create_post_data', [ self::class, 'translate_post_data' ], 10, 4 );
	}

	/**
	 * Translates post title and content via DeepL on quick-create.
	 *
	 * @param array   $post_data      The post data for wp_insert_post.
	 * @param WP_Post $source_post    The source post object.
	 * @param int     $source_blog_id The source blog ID.
	 * @param int     $target_blog_id The target blog ID.
	 *
	 * @return array
	 */
	public static function translate_post_data( array $post_data, WP_Post $source_post, int $source_blog_id, int $target_blog_id ): array {
		if ( ! function_exists( 'deepl_translate' ) ) {
			self::ensure_wpdeepl_loaded();
		}

		if ( ! function_exists( 'deepl_translate' ) ) {
			return $post_data;
		}

		try {
			$post_data = self::translate( $post_data, $source_blog_id, $target_blog_id );
		} catch ( Throwable $exception ) {
			// A DeepL or WP_Filesystem failure must not 500 the quick-create;
			// fall back to creating the draft from the untranslated source.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional: surface the failure in the host error log.
			\error_log( 'MSLS DeepL quick-create translation failed: ' . $exception->getMessage() );
		}

		return wp_slash( $post_data );
	}

	/**
	 * Runs the DeepL translation for the post title and content.
	 *
	 * @param array<string, mixed> $post_data      The post data for wp_insert_post.
	 * @param int                  $source_blog_id The source blog ID.
	 * @param int                  $target_blog_id The target blog ID.
	 *
	 * @return array<string, mixed> Post data with translated title and content.
	 */
	private static function translate( array $post_data, int $source_blog_id, int $target_blog_id ): array {
		$source_lang = MslsBlogCollection::get_blog_language( $source_blog_id );
		$target_lang = MslsBlogCollection::get_blog_language( $target_blog_id );

		$source_code = strtoupper( substr( $source_lang, 0, 2 ) );
		$target_code = strtoupper( substr( $target_lang, 0, 2 ) );

		// Translate title (plain text).
		$title_response = deepl_translate( $source_code, $target_code, [ 'post_title' => $post_data['post_title'] ] );

		if ( is_array( $title_response ) && ! empty( $title_response['success'] ) && isset( $title_response['translations']['post_title'] ) ) {
			$post_data['post_title'] = $title_response['translations']['post_title'];
		}

		// Swap blocks that must not be translated out for opaque placeholders.
		$preserved = [];
		$content   = self::extract_preserved_blocks( $post_data['post_content'], $preserved );

		// Wrap remaining block comments so DeepL's HTML parser leaves them intact.
		$content = preg_replace( '/<!--(.+?)-->/', '<x><!--$1--></x>', $content );

		$content_response = deepl_translate( $source_code, $target_code, [ 'post_content' => $content ] );

		if ( is_array( $content_response ) && ! empty( $content_response['success'] ) && isset( $content_response['translations']['post_content'] ) ) {
			$translated_content        = $content_response['translations']['post_content'];
			$translated_content        = preg_replace( '#<x>\s*(<!--.+?-->)\s*</x>#s', '$1', $translated_content );
			$translated_content        = self::restore_preserved_blocks( $translated_content, $preserved );
			$post_data['post_content'] = $translated_content;
		}

		return $post_data;
	}

	/**
	 * Replaces preserved blocks with opaque placeholder comments.
	 *
	 * @param string $content   Original post content.
	 * @param array  $preserved Out param. Receives extracted block strings.
	 *
	 * @return string Content with placeholders in place of preserved blocks.
	 */
	private static function extract_preserved_blocks( string $content, array &$preserved ): string {
		foreach ( self::PRESERVED_BLOCK_TYPES as $block_name ) {
			$quoted  = preg_quote( $block_name, '#' );
			$pattern = '#<!-- ' . $quoted . '\b.*?<!-- /' . $quoted . ' -->#s';

			$content = preg_replace_callback(
				$pattern,
				function ( $matches ) use ( &$preserved ) {
					$token       = '<!--MSLS_PRESERVE_' . count( $preserved ) . '-->';
					$preserved[] = $matches[0];
					return $token;
				},
				$content
			);
		}
		return $content;
	}

	/**
	 * Restores previously preserved blocks from their placeholder comments.
	 *
	 * @param string $content   Translated content containing placeholders.
	 * @param array  $preserved Block strings keyed by placeholder index.
	 *
	 * @return string Content with placeholders swapped back to original blocks.
	 */
	private static function restore_preserved_blocks( string $content, array $preserved ): string {
		if ( empty( $preserved ) ) {
			return $content;
		}

		return preg_replace_callback(
			'/<!--MSLS_PRESERVE_(\d+)-->/',
			function ( $matches ) use ( $preserved ) {
				$index = (int) $matches[1];
				return $preserved[ $index ] ?? $matches[0];
			},
			$content
		);
	}

	/**
	 * Loads the DeepL plugin files needed for translation in REST API context.
	 */
	private static function ensure_wpdeepl_loaded(): void {
		if ( function_exists( 'deepl_translate' ) ) {
			return;
		}

		$deepl_path = WP_PLUGIN_DIR . '/wpdeepl/';

		if ( ! file_exists( $deepl_path . 'wpdeepl.php' ) ) {
			return;
		}

		if ( ! defined( 'WPDEEPL_DEBUG' ) ) {
			define( 'WPDEEPL_DEBUG', false );
		}
		if ( ! defined( 'WPDEEPL_NAME' ) ) {
			define( 'WPDEEPL_NAME', 'wpdeepl/wpdeepl.php' );
		}
		if ( ! defined( 'WPDEEPL_SLUG' ) ) {
			define( 'WPDEEPL_SLUG', 'wpdeepl' );
		}
		if ( ! defined( 'WPDEEPL_PATH' ) ) {
			define( 'WPDEEPL_PATH', realpath( $deepl_path ) );
		}
		if ( ! defined( 'WPDEEPL_DIR' ) ) {
			define( 'WPDEEPL_DIR', realpath( $deepl_path ) );
		}
		if ( ! defined( 'WPDEEPL_URL' ) ) {
			define( 'WPDEEPL_URL', plugins_url( '', $deepl_path . 'wpdeepl.php' ) );
		}

		$upload_dir = wp_upload_dir();
		if ( ! defined( 'WPDEEPL_FILES' ) ) {
			define( 'WPDEEPL_FILES', trailingslashit( $upload_dir['basedir'] ) . 'wpdeepl' );
		}
		if ( ! defined( 'WPDEEPL_FILES_URL' ) ) {
			define( 'WPDEEPL_FILES_URL', trailingslashit( $upload_dir['baseurl'] ) . 'wpdeepl' );
		}

		$wpdeepl_plugin_data = get_file_data( $deepl_path . 'wpdeepl.php', [ 'Version' => 'Version' ], false );
		if ( ! defined( 'WPDEEPL_VERSION' ) ) {
			define( 'WPDEEPL_VERSION', $wpdeepl_plugin_data['Version'] );
		}
		if ( ! defined( 'WPDEEPL_FLAVOR' ) ) {
			define( 'WPDEEPL_FLAVOR', 'free' );
		}

		$files = [
			'deepl-configuration.class.php',
			'includes/deepl-functions.php',
			'client/deepl-data.class.php',
			'client/deeplapi.class.php',
			'client/deeplapi-functions.php',
			'client/deeplapi-translate.class.php',
		];

		foreach ( $files as $file ) {
			$full_path = trailingslashit( WPDEEPL_PATH ) . $file;
			if ( file_exists( $full_path ) ) {
				require_once $full_path;
			}
		}
	}
}
