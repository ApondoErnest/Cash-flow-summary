<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Login rate limiting (REQ-102)
    |--------------------------------------------------------------------------
    |
    | Failed login attempts are tracked per IP address and per username.
    |
    */

    'login' => [
        'max_attempts' => (int) env('LOGIN_RATE_LIMIT_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('LOGIN_RATE_LIMIT_DECAY_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session idle timeout (REQ-103)
    |--------------------------------------------------------------------------
    |
    | Defaults to SESSION_LIFETIME. Authenticated requests track last activity
    | in session; idle beyond this window forces logout.
    |
    */

    'session' => [
        'timeout_minutes' => (int) env('AUTH_SESSION_TIMEOUT_MINUTES', env('SESSION_LIFETIME', 120)),
        'last_activity_key' => 'auth.last_activity',
    ],

    /*
    |--------------------------------------------------------------------------
    | Password policy
    |--------------------------------------------------------------------------
    |
    | Applied when creating or changing passwords (UserService, Step 35+).
    | Login accepts existing passwords regardless of current policy.
    |
    */

    'password' => [
        'min_length' => (int) env('AUTH_PASSWORD_MIN_LENGTH', 12),
        'require_mixed_case' => env('AUTH_PASSWORD_MIXED_CASE', true),
        'require_numbers' => env('AUTH_PASSWORD_NUMBERS', true),
        'require_symbols' => env('AUTH_PASSWORD_SYMBOLS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Owner two-factor authentication (REQ-008)
    |--------------------------------------------------------------------------
    */

    'two_factor' => [
        'verified_session_key' => 'auth.two_factor.verified',
        'recovery_codes_count' => (int) env('AUTH_TWO_FACTOR_RECOVERY_CODES', 8),
        'issuer' => env('AUTH_TWO_FACTOR_ISSUER', env('APP_NAME', 'Cash Flow Summary')),
    ],

];
