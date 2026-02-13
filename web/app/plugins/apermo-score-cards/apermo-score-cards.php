<?php
/**
 * Plugin Name: Apermo Score Cards
 * Plugin URI: https://github.com/apermo/apermo-score-cards
 * Description: Gutenberg blocks for card and board game score cards with automatic calculations.
 * Version: 1.0.0
 * Author: Apermo
 * Author URI: https://apermo.de
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: apermo-score-cards
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.3
 *
 * @package ApermoScoreCards
 */

declare(strict_types=1);

namespace Apermo\ScoreCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASC_VERSION', '1.0.0' );
define( 'ASC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ASC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Load classes.
require_once ASC_PLUGIN_DIR . 'includes/class-capabilities.php';
require_once ASC_PLUGIN_DIR . 'includes/class-players.php';
require_once ASC_PLUGIN_DIR . 'includes/class-games.php';
require_once ASC_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once ASC_PLUGIN_DIR . 'includes/class-block-bindings.php';
require_once ASC_PLUGIN_DIR . 'includes/class-blocks.php';

// Load rendering classes.
require_once ASC_PLUGIN_DIR . 'includes/trait-base-render.php';
require_once ASC_PLUGIN_DIR . 'includes/class-game-renderer.php';
require_once ASC_PLUGIN_DIR . 'includes/class-darts-renderer.php';
require_once ASC_PLUGIN_DIR . 'includes/class-wizard-renderer.php';
require_once ASC_PLUGIN_DIR . 'includes/class-phase10-renderer.php';
require_once ASC_PLUGIN_DIR . 'includes/class-pool-renderer.php';

/**
 * Initialize the plugin.
 *
 * @return void
 */
function init(): void {
	load_plugin_textdomain(
		'apermo-score-cards',
		false,
		dirname( ASC_PLUGIN_BASENAME ) . '/languages'
	);

	Capabilities::init();
	Players::init();
	Games::init();
	REST_API::init();
	Block_Bindings::init();
	Blocks::init();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\init' );

/**
 * Plugin activation hook.
 *
 * @return void
 */
function activate(): void {
	Capabilities::register();
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\activate' );

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function deactivate(): void {
	Capabilities::unregister();
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, __NAMESPACE__ . '\deactivate' );