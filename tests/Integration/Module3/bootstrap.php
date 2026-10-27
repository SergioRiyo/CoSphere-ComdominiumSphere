<?php

/**
 * Fail before Laravel or RefreshDatabase can touch any database except the
 * explicitly dedicated local M3 test database.
 */
if (getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'pgsql'
    || getenv('DB_HOST') !== '127.0.0.1'
    || getenv('DB_DATABASE') !== 'cosphere_m3_test'
    || getenv('DB_URL')) {
    throw new RuntimeException('M3 PostgreSQL tests require APP_ENV=testing and the dedicated local cosphere_m3_test database.');
}

require dirname(__DIR__, 3).'/vendor/autoload.php';

/** Cached development configuration must never override the dedicated test connection. */
$configCache = sys_get_temp_dir().'/cosphere-m3-config-'.bin2hex(random_bytes(8)).'.php';
putenv('APP_CONFIG_CACHE='.$configCache);
$_ENV['APP_CONFIG_CACHE'] = $configCache;
$_SERVER['APP_CONFIG_CACHE'] = $configCache;
