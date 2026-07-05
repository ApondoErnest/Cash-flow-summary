<?php

declare(strict_types=1);

use App\Modules\Authentication\Livewire\Login;
use App\Modules\Authentication\Services\SessionService;
use App\Support\Auth\PasswordRules;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('login-ip:'.sha1('127.0.0.1'));
    RateLimiter::clear('login-username:'.sha1('owner'));
});

test('login is rate limited after max failed attempts', function () {
    $this->seed(DatabaseSeeder::class);
    Config::set('auth_security.login.max_attempts', 3);
    Config::set('auth_security.login.decay_minutes', 15);

    $component = Livewire::test(Login::class);

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $component
            ->set('username', 'owner')
            ->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors(['username']);
    }

    $component
        ->set('username', 'owner')
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['username' => __('auth.throttle', ['seconds' => RateLimiter::availableIn('login-ip:'.sha1('127.0.0.1'))])]);
});

test('successful login clears rate limit counters', function () {
    $this->seed(DatabaseSeeder::class);
    Config::set('auth_security.login.max_attempts', 3);

    $component = Livewire::test(Login::class);

    $component
        ->set('username', 'owner')
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['username']);

    $component
        ->set('username', env('SEED_OWNER_USERNAME', 'owner'))
        ->set('password', env('SEED_OWNER_PASSWORD', 'password'))
        ->call('authenticate')
        ->assertRedirect(route('password.change'));

    expect(RateLimiter::tooManyAttempts('login-ip:'.sha1('127.0.0.1'), 3))->toBeFalse();
});

test('idle session timeout logs user out and shows login message', function () {
    actingAsOwner();

    session([
        config('auth_security.session.last_activity_key') => now()->subMinutes(
            app(SessionService::class)->timeoutMinutes() + 1
        )->timestamp,
    ]);

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();

    $this->get(route('login'))
        ->assertOk()
        ->assertSee(__('auth.session_expired'), false);
});

test('idle session timeout redirects wire navigate requests to login', function () {
    actingAsOwner();

    session([
        config('auth_security.session.last_activity_key') => now()->subMinutes(
            app(SessionService::class)->timeoutMinutes() + 1
        )->timestamp,
    ]);

    $this->withHeader('X-Livewire-Navigate', '1')
        ->followingRedirects()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('auth.sign_in'), false);

    $this->assertGuest();
});

test('idle session timeout redirect uses a relative login path', function () {
    Config::set('app.url', 'http://localhost');

    actingAsOwner();

    session([
        config('auth_security.session.last_activity_key') => now()->subMinutes(
            app(SessionService::class)->timeoutMinutes() + 1
        )->timestamp,
    ]);

    $this->get(route('dashboard'))
        ->assertRedirect('/login');
});

test('active session is refreshed on authenticated requests', function () {
    actingAsOwner();

    $this->get(route('dashboard'))->assertOk();

    expect(session(config('auth_security.session.last_activity_key')))->toBeInt();
});

test('password policy rejects weak passwords', function () {
    $validator = Validator::make(
        ['password' => 'short'],
        ['password' => PasswordRules::defaults(confirmed: false)],
    );

    expect($validator->fails())->toBeTrue();

    $validator = Validator::make(
        ['password' => 'Str0ng!Passw0rd'],
        ['password' => PasswordRules::defaults(confirmed: false)],
    );

    expect($validator->passes())->toBeTrue();
});
