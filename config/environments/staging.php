<?php

/**
 * Staging environment configuration
 */

use Roots\WPConfig\Config;

Config::define('WP_DEBUG', true);
Config::define('WP_DEBUG_DISPLAY', false);
Config::define('WP_DEBUG_LOG', true);
Config::define('DISALLOW_INDEXING', true);

ini_set('display_errors', '0');
