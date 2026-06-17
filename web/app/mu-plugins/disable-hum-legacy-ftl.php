<?php
/**
 * Plugin Name: Disable Hum Legacy FTL Redirect
 * Description: Stops Hum decoding arbitrary 404 paths as base-32 post IDs and 301-redirecting them.
 */

namespace Apermo\HumLegacyFtl;

use Hum;

add_action( 'init', __NAMESPACE__ . '\\disable_legacy_ftl_redirect', 20 );

/**
 * Removes Hum's legacy "Friendly Twitter Links" decoder from the hum_legacy_id filter.
 *
 * Hum's Hum::legacy_ftl_id() runs on every 404, strips non-hex characters from the request
 * path and base_convert()s the remainder into a post ID to 301-redirect to. This hijacks
 * ordinary URLs (e.g. /blog/ decodes to post 11) and throws a ValueError when a long junk
 * path overflows base_convert() to INF on PHP 8. This site never used FTL shortlinks, so the
 * decoder is removed while Hum's modern /b/<id> shortlinks keep working.
 */
function disable_legacy_ftl_redirect(): void {
	global $wp_filter;

	if ( ! isset( $wp_filter['hum_legacy_id'] ) ) {
		return;
	}

	foreach ( $wp_filter['hum_legacy_id']->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$function = $callback['function'];

			if (
				\is_array( $function )
				&& $function[0] instanceof Hum
				&& $function[1] === 'legacy_ftl_id'
			) {
				remove_filter( 'hum_legacy_id', $function, $priority );
			}
		}
	}
}
