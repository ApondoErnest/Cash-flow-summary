#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * In-container application smoke checks (Step 111).
 * Invoked by deploy/smoke-test.sh — not part of the main Pest suite.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$failures = [];

try {
    if (Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'mysql') {
        $failures[] = 'DB driver is not mysql';
    }

    if (Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'cashflow_summary') {
        $failures[] = 'Unexpected database name: '.Illuminate\Support\Facades\DB::connection()->getDatabaseName();
    }

    if (! Illuminate\Support\Facades\Redis::connection()->ping()) {
        $failures[] = 'Redis ping failed';
    }

    if (config('queue.default') !== 'redis') {
        $failures[] = 'QUEUE_CONNECTION is not redis';
    }

    if (config('csv_imports.process_synchronously') !== false) {
        $failures[] = 'CSV_IMPORTS_SYNC must be false in Docker';
    }

    if (config('horizon.path') !== 'horizon') {
        $failures[] = 'Horizon path misconfigured';
    }

    /** @var Illuminate\Contracts\Http\Kernel $http */
    $http = $app->make(Illuminate\Contracts\Http\Kernel::class);

    $health = $http->handle(Illuminate\Http\Request::create('/up', 'GET'));
    if ($health->getStatusCode() !== 200) {
        $failures[] = '/up returned HTTP '.$health->getStatusCode();
    }

    $login = $http->handle(Illuminate\Http\Request::create('/login', 'GET'));
    if ($login->getStatusCode() !== 200) {
        $failures[] = '/login returned HTTP '.$login->getStatusCode();
    } elseif (! str_contains($login->getContent(), 'Sign in')) {
        $failures[] = '/login missing expected content';
    }
} catch (Throwable $exception) {
    $failures[] = $exception->getMessage();
}

if ($failures !== []) {
    fwrite(STDERR, "In-container smoke check failed:\n");
    foreach ($failures as $message) {
        fwrite(STDERR, "  - {$message}\n");
    }
    exit(1);
}

echo "In-container application smoke checks passed.\n";
