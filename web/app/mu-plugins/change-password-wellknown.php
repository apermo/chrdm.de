<?php
/**
 * Plugin Name: Change Password Well-Known
 * Description: Redirects /.well-known/change-password to the WordPress profile screen.
 */

namespace Apermo\ChangePassword;

add_action( 'init', __NAMESPACE__ . '\\maybe_redirect' );

/**
 * Redirects the well-known change-password URL to the profile screen.
 *
 * Password managers probe /.well-known/change-password; a 302 to the WordPress
 * profile lets them deep-link the user straight to the password form.
 * Unauthenticated visitors are bounced through wp-login and back by wp-admin
 * itself, so no extra handling is needed here.
 */
function maybe_redirect(): void {
	if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	$path = untrailingslashit(
		(string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), \PHP_URL_PATH ),
	);

	if ( $path !== '/.well-known/change-password' ) {
		return;
	}

	wp_safe_redirect( admin_url( 'profile.php' ), 302 );
	exit();
}
