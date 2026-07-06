<?php

declare(strict_types=1);

use App\Support\Security\ContentSecurityPolicyBuilder;
use App\Support\Security\ProductionSecurityBootstrap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('production_security.enabled', false);
    Config::set('production_security.force_https', false);
});

test('production security headers are omitted when hardening is disabled', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertHeaderMissing('Content-Security-Policy')
        ->assertHeaderMissing('Strict-Transport-Security');
});

test('production responses include csp hsts and baseline security headers when enabled', function () {
    Config::set('production_security.enabled', true);
    Config::set('production_security.force_https', true);
    Config::set('production_security.headers.csp', true);
    Config::set('production_security.hsts.enabled', true);
    Config::set('production_security.hsts.max_age', 31536000);
    Config::set('production_security.hsts.include_subdomains', true);
    Config::set('production_security.hsts.preload', false);

    $csp = app(ContentSecurityPolicyBuilder::class)->build(config('production_security.csp'));

    $this->get(route('login'))
        ->assertOk()
        ->assertHeader('Content-Security-Policy', $csp)
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

test('health endpoint receives security headers when hardening is enabled', function () {
    Config::set('production_security.enabled', true);
    Config::set('production_security.force_https', true);

    $this->get('/up')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('force https generates https urls when production hardening is enabled', function () {
    Config::set('app.url', 'http://app.example.test');
    Config::set('production_security.force_https', true);

    ProductionSecurityBootstrap::apply();

    expect(route('login', absolute: true))->toStartWith('https://');
    expect(URL::to('/dashboard'))->toStartWith('https://');
});

test('production security bootstrap marks session cookies secure when unset', function () {
    Config::set('session.secure', null);
    Config::set('production_security.force_https', true);

    ProductionSecurityBootstrap::apply();

    expect(config('session.secure'))->toBeTrue();
});

test('content security policy builder joins directives', function () {
    $policy = app(ContentSecurityPolicyBuilder::class)->build([
        'default-src' => "'self'",
        'script-src' => "'self' 'unsafe-inline'",
    ]);

    expect($policy)->toBe("default-src 'self'; script-src 'self' 'unsafe-inline'");
});
