<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (!isset($_SERVER['APP_ENV'])) {
    (new Dotenv())->usePutenv(true)->bootEnv(dirname(__DIR__) . '/.env');
}
// Set default timezone from env (APP_TIMEZONE or TZ). Force set to ensure consistency (DST handled by region).
$__tz = $_SERVER['APP_TIMEZONE'] ?? $_SERVER['TZ'] ?? getenv('APP_TIMEZONE') ?: getenv('TZ') ?: 'UTC';
@date_default_timezone_set($__tz);
