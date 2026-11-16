<?php

if (getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'pgsql'
    || getenv('DB_HOST') !== '127.0.0.1'
    || getenv('DB_PORT') !== '55435'
    || getenv('DB_DATABASE') !== 'cosphere_m5_test'
    || getenv('DB_USERNAME') !== 'cosphere_m5_test'
    || getenv('DB_SCHEMA') !== 'public'
    || getenv('DB_URL')) {
    throw new RuntimeException('M5 PostgreSQL tests require the dedicated local cosphere_m5_test database on port 55435.');
}

require dirname(__DIR__, 3).'/vendor/autoload.php';

$configCache = sys_get_temp_dir().'/cosphere-m5-config-'.bin2hex(random_bytes(8)).'.php';
putenv('APP_CONFIG_CACHE='.$configCache);
$_ENV['APP_CONFIG_CACHE'] = $configCache;
$_SERVER['APP_CONFIG_CACHE'] = $configCache;
