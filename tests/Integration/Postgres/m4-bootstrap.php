<?php

if (getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'pgsql'
    || getenv('DB_HOST') !== '127.0.0.1'
    || getenv('DB_PORT') !== '55434'
    || getenv('DB_DATABASE') !== 'cosphere_m4_test'
    || getenv('DB_URL')) {
    throw new RuntimeException('M4 PostgreSQL tests require the dedicated local cosphere_m4_test database on port 55434.');
}

require dirname(__DIR__, 3).'/vendor/autoload.php';

$configCache = sys_get_temp_dir().'/cosphere-m4-config-'.bin2hex(random_bytes(8)).'.php';
putenv('APP_CONFIG_CACHE='.$configCache);
$_ENV['APP_CONFIG_CACHE'] = $configCache;
$_SERVER['APP_CONFIG_CACHE'] = $configCache;
