<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Apermo\ScoreCards\Tests
 */

declare(strict_types=1);

namespace Apermo\ScoreCards\Tests;

// Define test paths.
define('TESTS_PATH', __DIR__);
define('PLUGIN_PATH', dirname(__DIR__, 2));

// Verify Composer dependencies.
$autoloader = PLUGIN_PATH . '/vendor/autoload.php';

if (! file_exists($autoloader)) {
    echo 'Please run "composer install" before running tests.' . PHP_EOL;
    exit(1);
}

// Define WordPress constants for stubs.
if (! defined('ABSPATH')) {
    define('ABSPATH', PLUGIN_PATH . '/');
}

if (! defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

// Load Composer autoloader.
require_once $autoloader;
