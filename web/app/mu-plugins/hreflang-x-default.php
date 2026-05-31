<?php
/**
 * Plugin Name: hreflang x-default
 * Description: Adds an x-default alternate to MSLS hreflang output when several translations exist.
 */

namespace Chrdm\HreflangXDefault;

add_filter( 'msls_output_get_alternate_links_arr', __NAMESPACE__ . '\\add_x_default' );

/**
 * Adds an x-default hreflang alternate to the MSLS head links.
 *
 * MSLS only emits x-default when a single alternate exists; with several
 * translations it omits it. This points x-default at the first listed
 * alternate — matching MSLS's own single-alternate behaviour — so search
 * engines have a fallback when no language matches the visitor.
 *
 * @param array $links Alternate <link> tags produced by MSLS.
 * @return array The links with an x-default entry prepended.
 */
function add_x_default( array $links ): array {
	if ( \count( $links ) < 2 ) {
		return $links;
	}

	foreach ( $links as $link ) {
		if ( \str_contains( $link, 'hreflang="x-default"' ) ) {
			return $links;
		}
	}

	$x_default = \preg_replace( '/hreflang="[^"]*"/', 'hreflang="x-default"', $links[0], 1 );

	if ( \is_string( $x_default ) && $x_default !== '' ) {
		\array_unshift( $links, $x_default );
	}

	return $links;
}
