<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Production security hardening (Step 102)
    |--------------------------------------------------------------------------
    |
    | Enables HTTPS enforcement, secure cookies, HSTS, and CSP headers.
    | Defaults to enabled when APP_ENV=production. See security-privacy.md.
    |
    */

    'enabled' => (bool) env('PRODUCTION_SECURITY_ENABLED', env('APP_ENV') === 'production'),

    'force_https' => (bool) env('FORCE_HTTPS', env('APP_ENV') === 'production'),

    'trusted_proxies' => env('TRUSTED_PROXIES', '*'),

    'hsts' => [
        'enabled' => (bool) env('SECURITY_HSTS_ENABLED', true),
        'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),
        'preload' => (bool) env('SECURITY_HSTS_PRELOAD', false),
    ],

    'headers' => [
        'csp' => (bool) env('SECURITY_CSP_ENABLED', true),
        'frame_options' => env('SECURITY_FRAME_OPTIONS', 'DENY'),
        'content_type_options' => env('SECURITY_CONTENT_TYPE_OPTIONS', 'nosniff'),
        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'permissions_policy' => env('SECURITY_PERMISSIONS_POLICY', 'camera=(), microphone=(), geolocation=()'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Livewire and Flux require 'unsafe-inline' for scripts/styles. Assets are
    | served from the application origin (Vite build + Bunny fonts bundled).
    |
    */

    'csp' => [
        'default-src' => "'self'",
        'base-uri' => "'self'",
        'form-action' => "'self'",
        'frame-ancestors' => "'none'",
        'img-src' => "'self' data: blob:",
        'font-src' => "'self' data:",
        'style-src' => "'self' 'unsafe-inline'",
        'script-src' => "'self' 'unsafe-inline'",
        'connect-src' => "'self'",
        'object-src' => "'none'",
    ],

];
