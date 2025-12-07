<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

// Ensure APP_* env can be read when running under Apache where $_SERVER may omit process env.
if (!isset($_SERVER['APP_ENV']) && false !== getenv('APP_ENV')) {
    $_SERVER['APP_ENV'] = getenv('APP_ENV');
}
if (!isset($_SERVER['APP_DEBUG']) && false !== getenv('APP_DEBUG')) {
    $_SERVER['APP_DEBUG'] = getenv('APP_DEBUG');
}

// If still no APP_ENV provided, fall back to loading .env (dev/local only).
if (!isset($_SERVER['APP_ENV'])) {
    (new Dotenv())->usePutenv(true)->bootEnv(dirname(__DIR__) . '/.env');
}

// Set default timezone from env (APP_TIMEZONE or TZ). Force set to ensure consistency (DST handled by region).
$__tz = $_SERVER['APP_TIMEZONE'] ?? $_SERVER['TZ'] ?? getenv('APP_TIMEZONE') ?: getenv('TZ') ?: 'UTC';
@date_default_timezone_set($__tz);
