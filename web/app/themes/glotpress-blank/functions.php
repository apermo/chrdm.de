<?php
/**
 * GlotPress Blank theme setup.
 *
 * @package GlotPress_Blank
 */

namespace Apermo\GlotPressBlank;

/**
 * Redirects the subsite front page to the GlotPress home.
 *
 * The translate.chrdm.de front page has no content of its own; GlotPress is
 * mounted under its default /glotpress/ base. Sending the bare domain there
 * lets visitors land on the translation UI instead of an empty WordPress page.
 * Only the front page is redirected — wp-admin, the REST API (including the
 * Traduttore webhook at /wp-json/) and GlotPress's own routes are untouched,
 * which is why GlotPress is left at /glotpress/ rather than mounted at root.
 */
function redirect_front_page_to_glotpress() {
	if ( ! \is_front_page() ) {
		return;
	}

	$glotpress_url = \function_exists( 'gp_url_public_root' )
		? \gp_url_public_root()
		: \home_url( '/glotpress/' );

	\wp_safe_redirect( $glotpress_url, 302 );
	exit;
}
\add_action( 'template_redirect', __NAMESPACE__ . '\\redirect_front_page_to_glotpress' );
