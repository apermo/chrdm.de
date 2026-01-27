<?php

/**
 * Bedrock wp-config.php
 *
 * This file is required by WordPress but we redirect to our custom configuration.
 * wp-config-ddev.php not needed
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/application.php';
require_once ABSPATH . 'wp-settings.php';
