<?php
/**
 * Plugin Name: Apermo Translation File Format
 * Description: Loads .mo directly for Traduttore-managed text domains so freshly deployed translations are never shadowed by a stale performant-translations .l10n.php cache.
 */

namespace Apermo\TranslationFormat;

/**
 * Text domains whose packs are built by the self-hosted GlotPress/Traduttore
 * platform and redeployed on every release.
 */
const DOMAINS = array( 'sovereignty', 'apermo-stash', 'apermo-notify' );

add_filter( 'translation_file_format', __NAMESPACE__ . '\\prefer_mo_for_managed_domains', 10, 2 );

/**
 * Forces the .mo format for the self-hosted, Traduttore-managed text domains.
 *
 * performant-translations compiles and prefers a .l10n.php cache next to each
 * .mo. For these domains the pack is replaced on every deploy, and a stale
 * .l10n.php from a previous build shadows the fresh .mo, so the site keeps
 * serving outdated strings. Loading the .mo directly for them keeps them
 * current; all other domains keep the .l10n.php optimisation.
 *
 * @param string $format Preferred translation file format.
 * @param string $domain Text domain being loaded.
 * @return string 'mo' for Traduttore-managed domains, the original format otherwise.
 */
function prefer_mo_for_managed_domains( $format, $domain ) {
	return \in_array( $domain, DOMAINS, true ) ? 'mo' : $format;
}
