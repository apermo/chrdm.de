<?php
/**
 * Plugin Name: Traduttore Content Directory
 * Description: Stores Traduttore language packs under uploads/ so they survive the read-only deploy hardening.
 */

namespace Apermo\Traduttore;

add_filter( 'traduttore.content_dir', __NAMESPACE__ . '\\content_dir' );
add_filter( 'traduttore.content_url', __NAMESPACE__ . '\\content_url' );

/**
 * Relocates Traduttore's language-pack cache into the uploads directory.
 *
 * Traduttore defaults to wp-content/traduttore, but the deploy locks the code
 * tree read-only with only uploads/ left writable, so it can neither create
 * nor write that directory. uploads/ is writable, survives every deploy and is
 * web-accessible, which the downloadable packs require. Git clones are
 * unaffected — Traduttore keeps those in the system temp directory.
 *
 * @param string $dir Default cache directory path.
 * @return string Cache directory under uploads.
 */
function content_dir( string $dir ): string {
	return wp_get_upload_dir()['basedir'] . '/traduttore';
}

/**
 * Mirrors the cache relocation for the public language-pack download URL.
 *
 * Keeps the URL Traduttore advertises in its translations API in sync with
 * content_dir(), so the consumer's translation downloader fetches packs from
 * the uploads location rather than the unused wp-content default.
 *
 * @param string $url Default cache directory URL.
 * @return string Cache URL under uploads.
 */
function content_url( string $url ): string {
	return wp_get_upload_dir()['baseurl'] . '/traduttore';
}
