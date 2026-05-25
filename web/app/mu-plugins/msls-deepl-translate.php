<?php
/**
 * Plugin Name: MSLS DeepL Translate
 * Description: Translates title and content via DeepL when using MSLS quick-create.
 */

add_action( 'rest_api_init', 'msls_deepl_register_filter', 99 );

/**
 * Replace the default "From xx:" prefix with DeepL translation.
 */
function msls_deepl_register_filter(): void {
	remove_filter( 'msls_quick_create_post_data', array( lloc\Msls\MslsRestApi::class, 'prefix_source_language' ) );
	add_filter( 'msls_quick_create_post_data', 'msls_deepl_translate_post_data', 10, 4 );
}

/**
 * Translate post title and content via DeepL on quick-create.
 *
 * @param array    $post_data      The post data for wp_insert_post.
 * @param \WP_Post $source_post    The source post object.
 * @param int      $source_blog_id The source blog ID.
 * @param int      $target_blog_id The target blog ID.
 *
 * @return array
 */
function msls_deepl_translate_post_data( array $post_data, \WP_Post $source_post, int $source_blog_id, int $target_blog_id ): array {
	if ( ! function_exists( 'deepl_translate' ) ) {
		msls_deepl_ensure_loaded();
	}

	if ( ! function_exists( 'deepl_translate' ) ) {
		return $post_data;
	}

	$source_lang = lloc\Msls\MslsBlogCollection::get_blog_language( $source_blog_id );
	$target_lang = lloc\Msls\MslsBlogCollection::get_blog_language( $target_blog_id );

	$source_code = strtoupper( substr( $source_lang, 0, 2 ) );
	$target_code = strtoupper( substr( $target_lang, 0, 2 ) );

	// Translate title (plain text).
	$title_response = deepl_translate( $source_code, $target_code, array( 'post_title' => $post_data['post_title'] ) );

	if ( is_array( $title_response ) && ! empty( $title_response['success'] ) && isset( $title_response['translations']['post_title'] ) ) {
		$post_data['post_title'] = $title_response['translations']['post_title'];
	}

	// Translate content — wrap block comments so DeepL's HTML parser doesn't choke.
	$content = preg_replace( '/<!--(.+?)-->/', '<x><!--$1--></x>', $post_data['post_content'] );

	$content_response = deepl_translate( $source_code, $target_code, array( 'post_content' => $content ) );

	if ( is_array( $content_response ) && ! empty( $content_response['success'] ) && isset( $content_response['translations']['post_content'] ) ) {
		$translated_content = $content_response['translations']['post_content'];
		$translated_content = preg_replace( '#<x>\s*(<!--.+?-->)\s*</x>#s', '$1', $translated_content );
		$post_data['post_content'] = $translated_content;
	}

	return $post_data;
}

/**
 * Load the DeepL plugin files needed for translation in REST API context.
 */
function msls_deepl_ensure_loaded(): void {
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

	$wpdeepl_plugin_data = get_file_data( $deepl_path . 'wpdeepl.php', array( 'Version' => 'Version' ), false );
	if ( ! defined( 'WPDEEPL_VERSION' ) ) {
		define( 'WPDEEPL_VERSION', $wpdeepl_plugin_data['Version'] );
	}
	if ( ! defined( 'WPDEEPL_FLAVOR' ) ) {
		define( 'WPDEEPL_FLAVOR', 'free' );
	}

	$files = array(
		'deepl-configuration.class.php',
		'includes/deepl-functions.php',
		'client/deepl-data.class.php',
		'client/deeplapi.class.php',
		'client/deeplapi-functions.php',
		'client/deeplapi-translate.class.php',
	);

	foreach ( $files as $file ) {
		$full_path = trailingslashit( WPDEEPL_PATH ) . $file;
		if ( file_exists( $full_path ) ) {
			include_once $full_path;
		}
	}
}
