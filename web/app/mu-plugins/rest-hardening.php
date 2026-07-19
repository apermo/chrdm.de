<?php
/**
 * Plugin Name: REST API Hardening
 * Description: Restricts core wp/v2 and batch REST routes to authenticated users to cut user enumeration and content-scraping surface.
 */

namespace Apermo\RestHardening;

use WP_Error;

add_filter( 'rest_authentication_errors', __NAMESPACE__ . '\\require_authentication' );

/**
 * Denies unauthenticated access to the core wp/v2 and batch REST routes.
 *
 * Core exposes /wp/v2 (notably /wp/v2/users) and /batch/v1 to anonymous
 * callers by default, which enables username enumeration, bulk content
 * scraping and the noisy POST /batch/v1 probes seen in error monitoring.
 * Every other namespace already gates itself through its own permission
 * callbacks — public-by-design routes (oEmbed, ActivityPub federation,
 * Contact Form 7 submissions, the Friends browser extension) stay reachable,
 * and internal _embed dispatch bypasses this authentication filter entirely,
 * so post embeds keep resolving their author data.
 *
 * @param WP_Error|bool|null $result Prior authentication result from earlier filters.
 * @return WP_Error|bool|null The prior result, or a 401 error for protected routes.
 */
function require_authentication( WP_Error|bool|null $result ): WP_Error|bool|null {
	// Respect a determination another handler already made (true or WP_Error).
	if ( ! empty( $result ) ) {
		return $result;
	}

	if ( is_user_logged_in() ) {
		return $result;
	}

	if ( ! is_protected_route( current_route() ) ) {
		return $result;
	}

	return new WP_Error(
		'rest_authentication_required',
		__( 'This REST endpoint is restricted to authenticated users.', 'rest-hardening' ),
		[ 'status' => rest_authorization_required_code() ],
	);
}

/**
 * Resolves the REST route being served for the current request.
 *
 * Reads the route WordPress parsed from the request, which covers both the
 * pretty /wp-json/<route> form and the plain ?rest_route=<route> fallback.
 *
 * @return string The REST route (e.g. /wp/v2/users), or an empty string.
 */
function current_route(): string {
	if ( isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
		return (string) $GLOBALS['wp']->query_vars['rest_route'];
	}

	return '';
}

/**
 * Determines whether a route belongs to the authenticated-only core namespaces.
 *
 * @param string $route The REST route to test.
 * @return bool True when anonymous access to the route must be denied.
 */
function is_protected_route( string $route ): bool {
	foreach ( [ '/wp/v2', '/batch/' ] as $prefix ) {
		if ( \str_starts_with( $route, $prefix ) ) {
			return true;
		}
	}

	return false;
}
