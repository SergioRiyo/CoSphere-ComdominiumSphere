<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

require __DIR__.'/bootstrap.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

config(['inertia.ssr.enabled' => false]);
Carbon::setTestNow('2026-09-30 10:00:00');
Date::setTestNow('2026-09-30 10:00:00');
$operation = json_decode(base64_decode($argv[1]), true, flags: JSON_THROW_ON_ERROR);
Auth::guard('web')->setUser(User::findOrFail($operation['actor']));
DB::statement("SET lock_timeout = '15s'");
echo 'READY '.DB::scalar('SELECT pg_backend_pid()').PHP_EOL;
flush();

$request = Request::create($operation['url'], $operation['method'], server: [
    'HTTP_ACCEPT' => 'application/json',
    'CONTENT_TYPE' => 'application/json',
], content: json_encode($operation['data'] ?? [], JSON_THROW_ON_ERROR));
$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request);
echo 'RESULT '.json_encode([
    'status' => $response->getStatusCode(),
    'body' => json_decode($response->getContent(), true),
], JSON_THROW_ON_ERROR).PHP_EOL;
$kernel->terminate($request, $response);
