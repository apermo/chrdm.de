<?php

/**
 * Production environment configuration
 */

use Roots\WPConfig\Config;

Config::define('WP_DEBUG', false);
Config::define('WP_DEBUG_DISPLAY', false);
Config::define('WP_DEBUG_LOG', false);
Config::define('DISALLOW_INDEXING', false);

ini_set('display_errors', '0');
