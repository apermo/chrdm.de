<?php
/**
 * Plugin Name: Sentry Admin Context
 * Description: Attaches full user identity to Sentry events for administrators only.
 */

namespace Apermo\Sentry;

add_filter( 'wp_sentry_user_context', __NAMESPACE__ . '\\enrich_admin_context' );

/**
 * Adds the current user's full identity to Sentry events for administrators.
 *
 * Visitors stay minimized: WP_SENTRY_SEND_DEFAULT_PII is false, and the WP Sentry
 * plugin only resolves user context for logged-in users, passing non-admins just
 * their numeric id. The sole administrator is both the data subject and the
 * controller, so logging their own identity needs no consent and makes their own
 * error reports actionable.
 *
 * @param array $user User context the WP Sentry plugin is about to send.
 * @return array Possibly enriched user context.
 */
function enrich_admin_context( array $user ): array {
	// mu-plugins load before pluggable functions, so an error captured very
	// early in bootstrap could invoke this filter before current_user_can exists.
	if ( ! \function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
		return $user;
	}

	$current = wp_get_current_user();

	$user['id']         = $current->ID;
	$user['name']       = $current->display_name;
	$user['username']   = $current->user_login;
	$user['email']      = $current->user_email;
	$user['ip_address'] = isset( $_SERVER['REMOTE_ADDR'] )
		? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
		: null;

	return $user;
}
