#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Production login diagnostics — run inside the app container on the VPS.
 *
 *   ./deploy/compose-production.sh exec app php deploy/diagnose-login-production.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Modules\Authentication\Livewire\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

$failures = [];
$notes = [];

function note(string $message): void
{
    global $notes;
    $notes[] = $message;
    echo "  · {$message}\n";
}

function fail(string $message): void
{
    global $failures;
    $failures[] = $message;
    fwrite(STDERR, "  ✗ {$message}\n");
}

echo "=== Cash Flow login diagnostics (production) ===\n\n";

echo "1. Application config\n";

if (! config('app.key')) {
    fail('APP_KEY is empty — run config:clear and ensure compose env_file is set');
} else {
    note('APP_KEY is set');
}

note('APP_URL='.config('app.url'));
note('SESSION_DRIVER='.config('session.driver'));
note('SESSION_SECURE='.json_encode(config('session.secure')));
note('SESSION_DOMAIN='.json_encode(config('session.domain')));
note('SESSION_ENCRYPT='.json_encode(config('session.encrypt')));

if (is_file(base_path('bootstrap/cache/config.php'))) {
    fail('bootstrap/cache/config.php exists — run: php artisan config:clear');
} else {
    note('No cached config.php (good for env changes)');
}

if (is_file(base_path('bootstrap/cache/routes-v7.php'))) {
    note('routes-v7.php cache exists — if login fails, run route:clear');
}

echo "\n2. Redis + session write\n";

try {
    if (! Illuminate\Support\Facades\Redis::connection()->ping()) {
        fail('Redis ping failed');
    } else {
        note('Redis ping OK');
    }

    $session = app('session.store');
    $session->put('_diagnose_login', 'ok');
    $session->save();

    if ($session->get('_diagnose_login') !== 'ok') {
        fail('Session write/read failed');
    } else {
        note('Session write/read OK (driver: '.config('session.driver').')');
    }
} catch (Throwable $exception) {
    fail('Session/Redis error: '.$exception->getMessage());
}

echo "\n3. Owner account\n";

$owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->first();

if ($owner === null) {
    fail('No owner user — run: php artisan migrate --force --seed');
} else {
    note('Owner user id='.$owner->id.' active='.($owner->is_active ? 'yes' : 'no'));

    $seedPassword = (string) env('SEED_OWNER_PASSWORD', 'password');

    if (! Hash::check($seedPassword, (string) $owner->password)) {
        fail('Owner password is not the default seed password — reset in DB or use the correct password');
    } else {
        note('Owner password matches default seed password');
    }
}

echo "\n4. HTTPS / proxy detection (simulated browser via host nginx)\n";

/** @var Illuminate\Contracts\Http\Kernel $http */
$http = $app->make(Illuminate\Contracts\Http\Kernel::class);

$proxyRequest = Request::create(
    uri: '/login',
    method: 'GET',
    server: [
        'HTTP_HOST' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'cashflow.gsautobilan.com',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.1',
        'REMOTE_ADDR' => '203.0.113.1',
        'HTTPS' => 'off',
    ],
);

$proxyResponse = $http->handle($proxyRequest);
$proxyRequest = Request::createFromBase($proxyRequest);

if (! $proxyRequest->isSecure()) {
    fail('Request is not secure with X-Forwarded-Proto=https — host nginx must send proxy_set_header X-Forwarded-Proto $scheme on :443');
} else {
    note('TrustProxies sees HTTPS when X-Forwarded-Proto=https');
}

if ($proxyResponse->getStatusCode() !== 200) {
    fail('/login GET returned HTTP '.$proxyResponse->getStatusCode());
} else {
    note('/login GET HTTP 200 (simulated HTTPS request)');
}

$cookies = $proxyResponse->headers->getCookies();
$sessionCookieName = config('session.cookie');

$sessionCookie = null;
foreach ($cookies as $cookie) {
    if ($cookie->getName() === $sessionCookieName) {
        $sessionCookie = $cookie;
        break;
    }
}

if ($sessionCookie === null) {
    fail('No session cookie on /login response — session driver or middleware issue');
} else {
    note('Session cookie issued: '.$sessionCookieName);

    if (config('session.secure') && ! $sessionCookie->isSecure()) {
        fail('Session cookie is not Secure while SESSION_SECURE_COOKIE=true');
    } else {
        note('Session cookie Secure flag OK');
    }
}

echo "\n5. Livewire login (server-side)\n";

try {
    $component = Livewire::test(Login::class)
        ->set('username', env('SEED_OWNER_USERNAME', 'owner'))
        ->set('password', env('SEED_OWNER_PASSWORD', 'password'))
        ->call('authenticate');

    $effects = $component->effects;

    if (! isset($effects['redirect']) && ! isset($effects['url'])) {
        $errors = $component->errors();
        if ($errors !== []) {
            fail('Livewire validation errors: '.json_encode($errors));
        }
        fail('Livewire authenticate did not redirect — check validation errors or auth flow');
    } else {
        $target = $effects['redirect'] ?? $effects['url'] ?? 'unknown';
        note('Livewire authenticate redirected to: '.$target);
    }
} catch (Throwable $exception) {
    fail('Livewire login test failed: '.$exception->getMessage());
}

echo "\n6. Livewire assets on login page\n";

$loginHtml = $proxyResponse->getContent();

if (! preg_match('#(/livewire-[a-f0-9]+/livewire(?:\.min)?\.js)#', $loginHtml, $matches)) {
    fail('Login HTML missing Livewire JS URL — Livewire scripts not rendered');
} else {
    $livewireJsPath = $matches[1];
    note('Login page references '.$livewireJsPath);

    $livewireJs = $http->handle(Request::create($livewireJsPath, 'GET'));
    if ($livewireJs->getStatusCode() !== 200) {
        fail($livewireJsPath.' returned HTTP '.$livewireJs->getStatusCode().' — run route:clear');
    } else {
        note($livewireJsPath.' HTTP 200');
    }
}

if (! str_contains($loginHtml, 'livewire-') || ! str_contains($loginHtml, '/update')) {
    fail('Login HTML missing Livewire update endpoint hints');
}

$manifestPath = public_path('build/manifest.json');
if (! is_file($manifestPath)) {
    fail('Missing public/build/manifest.json — rebuild images');
} else {
    note('Vite manifest present');
}

echo "\n";

if ($failures !== []) {
    fwrite(STDERR, "Login diagnostics FAILED:\n");
    foreach ($failures as $message) {
        fwrite(STDERR, "  - {$message}\n");
    }
    fwrite(STDERR, "\nSuggested fixes on VPS:\n");
    fwrite(STDERR, "  ./deploy/compose-production.sh exec app php artisan config:clear\n");
    fwrite(STDERR, "  ./deploy/compose-production.sh exec app php artisan route:clear\n");
    fwrite(STDERR, "  ./deploy/compose-production.sh exec app php artisan view:clear\n");
    fwrite(STDERR, "  ./deploy/compose-production.sh restart app nginx\n");
    fwrite(STDERR, "  sudo grep -R X-Forwarded-Proto /etc/nginx/sites-enabled/\n");
    exit(1);
}

echo "All login diagnostics passed in-container.\n";
echo "If the browser still spins, open DevTools → Network → click Sign in → check POST /livewire/update status (419/500/502).\n";
