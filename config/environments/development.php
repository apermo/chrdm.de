<?php

/**
 * Development environment configuration
 */

use Roots\WPConfig\Config;

Config::define('WP_DEBUG', true);
Config::define('WP_DEBUG_DISPLAY', true);
Config::define('WP_DEBUG_LOG', true);
Config::define('SCRIPT_DEBUG', true);
Config::define('SAVEQUERIES', true);
Config::define('DISALLOW_INDEXING', true);

ini_set('display_errors', '1');
