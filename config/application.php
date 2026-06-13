<?php

/**
 * Application configuration
 *
 * This is the main configuration file for the WordPress application.
 * It loads environment variables and sets up WordPress configuration.
 */

use Roots\WPConfig\Config;
use function Env\env;

/**
 * Directory containing all application files
 */
$root_dir = dirname(__DIR__);

/**
 * Document root (web directory)
 */
$webroot_dir = $root_dir . '/web';

/**
 * Load environment variables
 * Dotenv loads from .env file in the project root
 */
if (file_exists($root_dir . '/.env')) {
    $dotenv = Dotenv\Dotenv::createUnsafeImmutable($root_dir);
    $dotenv->load();
    $dotenv->required(['WP_HOME', 'WP_SITEURL']);
    if (!env('DATABASE_URL')) {
        $dotenv->required(['DB_NAME', 'DB_USER', 'DB_PASSWORD']);
    }
}

/**
 * Set environment type
 */
$env_type = env('WP_ENV') ?: 'production';

/**
 * URLs
 */
Config::define('WP_HOME', env('WP_HOME'));
Config::define('WP_SITEURL', env('WP_SITEURL'));

/**
 * Custom content directory
 */
Config::define('CONTENT_DIR', '/app');
Config::define('WP_CONTENT_DIR', $webroot_dir . '/app');
Config::define('WP_CONTENT_URL', Config::get('WP_HOME') . '/app');

/**
 * Database configuration
 */
if (env('DATABASE_URL')) {
    $dsn = (object) parse_url(env('DATABASE_URL'));

    Config::define('DB_NAME', substr($dsn->path, 1));
    Config::define('DB_USER', $dsn->user);
    Config::define('DB_PASSWORD', isset($dsn->pass) ? $dsn->pass : null);
    Config::define('DB_HOST', isset($dsn->port) ? "{$dsn->host}:{$dsn->port}" : $dsn->host);
} else {
    Config::define('DB_NAME', env('DB_NAME'));
    Config::define('DB_USER', env('DB_USER'));
    Config::define('DB_PASSWORD', env('DB_PASSWORD'));
    Config::define('DB_HOST', env('DB_HOST') ?: 'localhost');
}

Config::define('DB_CHARSET', 'utf8mb4');
Config::define('DB_COLLATE', '');
$table_prefix = env('DB_PREFIX') ?: 'wp_';

/**
 * Authentication keys and salts
 */
Config::define('AUTH_KEY', env('AUTH_KEY'));
Config::define('SECURE_AUTH_KEY', env('SECURE_AUTH_KEY'));
Config::define('LOGGED_IN_KEY', env('LOGGED_IN_KEY'));
Config::define('NONCE_KEY', env('NONCE_KEY'));
Config::define('AUTH_SALT', env('AUTH_SALT'));
Config::define('SECURE_AUTH_SALT', env('SECURE_AUTH_SALT'));
Config::define('LOGGED_IN_SALT', env('LOGGED_IN_SALT'));
Config::define('NONCE_SALT', env('NONCE_SALT'));

/**
 * Custom settings
 */
Config::define('AUTOMATIC_UPDATER_DISABLED', true);
Config::define('DISABLE_WP_CRON', env('DISABLE_WP_CRON') ?: false);
Config::define('DISALLOW_FILE_EDIT', true);
Config::define('DISALLOW_FILE_MODS', true);
Config::define('WP_DEFAULT_THEME', 'sovereignty');

/**
 * Multisite configuration
 */
Config::define('WP_ALLOW_MULTISITE', env('WP_ALLOW_MULTISITE') ?: false);
Config::define('MULTISITE', env('MULTISITE') ?: false);
Config::define('SUBDOMAIN_INSTALL', env('SUBDOMAIN_INSTALL') ?: true);
Config::define('DOMAIN_CURRENT_SITE', env('DOMAIN_CURRENT_SITE') ?: 'christoph-daum.de');
Config::define('PATH_CURRENT_SITE', env('PATH_CURRENT_SITE') ?: '/');
Config::define('SITE_ID_CURRENT_SITE', env('SITE_ID_CURRENT_SITE') ?: 1);
Config::define('BLOG_ID_CURRENT_SITE', env('BLOG_ID_CURRENT_SITE') ?: 1);
Config::define('COOKIE_DOMAIN', env('COOKIE_DOMAIN') ?: '');
Config::define('SUNRISE', 'on');

/**
 * Force direct filesystem access.
 *
 * Deployed files are owned by the account user but the content directories
 * are read-only, so WordPress's filesystem-method probe fails its write test
 * and falls back to the FTP transport. With no FTP credentials that transport
 * fatals on a null connection whenever code merely reads a file via
 * WP_Filesystem (e.g. wpdeepl reading languages.csv during MSLS quick-create).
 */
Config::define('FS_METHOD', env('FS_METHOD') ?: 'direct');

/**
 * Debugging settings
 */
Config::define('WP_DEBUG', env('WP_DEBUG') ?: false);
Config::define('WP_DEBUG_DISPLAY', env('WP_DEBUG_DISPLAY') ?: false);
Config::define('WP_DEBUG_LOG', env('WP_DEBUG_LOG') ?: false);
Config::define('SCRIPT_DEBUG', env('SCRIPT_DEBUG') ?: false);
Config::define('SAVEQUERIES', env('SAVEQUERIES') ?: false);

ini_set('display_errors', '0');

/**
 * Sentry error monitoring (server-side only).
 *
 * PHP-only by design: no browser DSN is set, so no third-party JavaScript loads
 * on the frontend and no client data leaves the browser, keeping the site
 * consent-free. The DSN lives only in production's .env, so staging and local
 * development stay silent without further configuration. PII is disabled, so
 * visitor IPs and identities are never sent; administrators are enriched
 * separately by the sentry-admin-context mu-plugin.
 */
$sentry_dsn = env('WP_SENTRY_PHP_DSN');
if ($sentry_dsn) {
    Config::define('WP_SENTRY_PHP_DSN', $sentry_dsn);
    Config::define('WP_SENTRY_ENV', env('WP_SENTRY_ENV') ?: $env_type);
    Config::define('WP_SENTRY_SEND_DEFAULT_PII', false);
    Config::define('WP_SENTRY_TRACES_SAMPLE_RATE', 1.0);
    Config::define('WP_SENTRY_ENABLE_LOGS', true);
}

/**
 * Allow WordPress CLI to work
 */
if (defined('WP_CLI') && WP_CLI) {
    $_SERVER['HTTP_HOST'] = Config::get('DOMAIN_CURRENT_SITE');
}

/**
 * Per-host configuration
 */
switch ($_SERVER['HTTP_HOST'] ?? '') {
    case 'christoph-daum.com':
        Config::define('HUM_SHORTLINK_BASE', 'https://en.chrdm.de');
        break;
    default:
        Config::define('HUM_SHORTLINK_BASE', 'https://chrdm.de');
        break;
}

/**
 * Load environment specific configuration
 */
$env_config = __DIR__ . '/environments/' . $env_type . '.php';

if (file_exists($env_config)) {
    require_once $env_config;
}

Config::apply();

/**
 * Bootstrap WordPress
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', $webroot_dir . '/wp/');
}
