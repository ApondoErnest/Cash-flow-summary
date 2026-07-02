<?php

declare(strict_types=1);

use App\Modules\Authentication\Livewire\Login;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);
test('login page renders midnight finance guest layout', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee('data-mf-login-brand', false);
    $response->assertSee('data-mf-login-form', false);
    $response->assertSee('Sign in', false);
    $response->assertSee('Username', false);
    $response->assertDontSee('mf-auth-logout', false);
});

test('guest is redirected to login from dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated user is redirected away from login', function () {
    actingAsOwner();

    $this->get(route('login'))->assertRedirect(route('dashboard'));
});

test('owner can sign in with username and password', function () {
    $this->seed(DatabaseSeeder::class);

    Livewire::test(Login::class)
        ->set('username', env('SEED_OWNER_USERNAME', 'owner'))
        ->set('password', env('SEED_OWNER_PASSWORD', 'password'))
        ->call('authenticate')
        ->assertRedirect(route('password.change'));

    $this->assertAuthenticatedAs(
        \App\Models\User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->first(),
    );
});

test('invalid credentials show validation error', function () {
    $this->seed(DatabaseSeeder::class);

    Livewire::test(Login::class)
        ->set('username', 'owner')
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['username']);

    $this->assertGuest();
});

test('invalid credentials show french error when locale is french', function () {
    $this->seed(DatabaseSeeder::class);

    Livewire::test(Login::class)
        ->set('locale', 'fr')
        ->set('username', 'owner')
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['username' => __('auth.failed')]);
});

test('authenticated user can log out', function () {
    actingAsOwner();

    $this->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    $this->get(route('login'))->assertOk();
});
