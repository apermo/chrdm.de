<?php
/**
 * Plugin Name: Robots Content Signal
 * Description: Adds Content-Signal AI-usage preferences and AI-crawler rules to robots.txt.
 */

namespace Apermo\RobotsContentSignal;

add_filter( 'robots_txt', __NAMESPACE__ . '\\filter', 99, 2 );

/**
 * Appends AI-usage preferences to the generated robots.txt.
 *
 * Declares Content-Signal (search=yes, ai-input=yes, ai-train=no) in the
 * default group and opts known training crawlers out entirely, while search
 * engines and user-initiated retrieval bots stay allowed via the * group.
 *
 * @param string $output The generated robots.txt body.
 * @param bool   $public Whether the site asks to be indexed.
 * @return string The robots.txt body with AI-usage rules appended.
 */
function filter( string $output, bool $public ): string {
	if ( ! $public ) {
		return $output;
	}

	$signal = 'Content-Signal: search=yes, ai-input=yes, ai-train=no';

	if ( ! \str_contains( $output, 'Content-Signal:' ) ) {
		if ( \str_contains( $output, 'User-agent: *' ) ) {
			$output = \preg_replace( '/^User-agent: \*\R/m', '${0}' . $signal . "\n", $output, 1 );
		} else {
			$output = "User-agent: *\n{$signal}\n\n" . $output;
		}
	}

	// Crawlers used to build training corpora — opted out for ai-train=no.
	// Search bots (e.g. Applebot, Googlebot) and user-initiated retrieval bots
	// (OAI-SearchBot, ChatGPT-User, PerplexityBot) remain allowed by the * group.
	$blocked = [
		'GPTBot',
		'ClaudeBot',
		'anthropic-ai',
		'CCBot',
		'Google-Extended',
		'Applebot-Extended',
		'Bytespider',
		'Meta-ExternalAgent',
	];

	$rules = "\n# ai-train=no — opt training crawlers out (see Content-Signal above).\n";
	foreach ( $blocked as $agent ) {
		$rules .= "User-agent: {$agent}\nDisallow: /\n\n";
	}

	return \rtrim( $output ) . "\n" . \rtrim( $rules ) . "\n";
}
