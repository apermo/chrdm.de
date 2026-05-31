<?php
/**
 * Plugin Name: Security Headers
 * Description: Sends hardened HTTP security response headers on front-end, admin and login responses.
 */

namespace Apermo\SecurityHeaders;

add_action( 'send_headers', __NAMESPACE__ . '\\send', 99 );
add_action( 'login_init', __NAMESPACE__ . '\\send', 99 );
add_action( 'admin_init', __NAMESPACE__ . '\\send', 99 );

/**
 * Emits the security headers, adapting a few values to the request context.
 *
 * Values follow https://specification.website/spec/security/. HSTS is sent
 * only over TLS, and frame denial is skipped on WordPress's own post-embed
 * responses so the oEmbed feature keeps working when other sites embed us.
 */
function send(): void {
	if ( \headers_sent() ) {
		return;
	}

	\header( 'X-Content-Type-Options: nosniff' );
	\header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	\header( 'Cross-Origin-Opener-Policy: same-origin' );
	\header( 'Cross-Origin-Resource-Policy: same-site' );
	\header(
		'Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), '
		. 'magnetometer=(), microphone=(), payment=(), usb=(), interest-cohort=()',
	);

	// HSTS commits clients to HTTPS; only ever send it over a secure connection.
	// `preload` is intentionally omitted until every *.christoph-daum.de subdomain
	// is confirmed HTTPS-only — submitting to the preload list is hard to reverse.
	if ( is_ssl() ) {
		\header( 'Strict-Transport-Security: max-age=63072000; includeSubDomains' );
	}

	// Block framing everywhere except WordPress post embeds, which are meant to
	// be iframed cross-origin by other sites.
	if ( ! ( \function_exists( 'is_embed' ) && is_embed() ) ) {
		\header( 'X-Frame-Options: DENY' );
	}
}
