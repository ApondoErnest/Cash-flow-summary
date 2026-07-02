<?php

declare(strict_types=1);

use App\Modules\Authentication\Livewire\ChangePassword;
use App\Modules\Authentication\Livewire\Login;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const STRONG_PASSWORD = 'Str0ng!Passw0rd';

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('owner with temporary password is redirected to change password after login', function () {
    Livewire::test(Login::class)
        ->set('username', env('SEED_OWNER_USERNAME', 'owner'))
        ->set('password', env('SEED_OWNER_PASSWORD', 'password'))
        ->call('authenticate')
        ->assertRedirect(route('password.change'));

    $this->assertAuthenticated();
});

test('dashboard is blocked until temporary password is changed', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertRedirect(route('password.change'));
});

test('owner can change temporary password and reach dashboard', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();

    $this->actingAs($owner);

    Livewire::test(ChangePassword::class)
        ->set('currentPassword', env('SEED_OWNER_PASSWORD', 'password'))
        ->set('password', STRONG_PASSWORD)
        ->set('password_confirmation', STRONG_PASSWORD)
        ->call('updatePassword')
        ->assertRedirect(route('center.select'));

    expect($owner->fresh()->must_change_password)->toBeFalse();
    expect(Hash::check(STRONG_PASSWORD, $owner->fresh()->password))->toBeTrue();
    expect(Hash::check(env('SEED_OWNER_PASSWORD', 'password'), $owner->fresh()->password))->toBeFalse();

    $this->get(route('center.select'))->assertOk();
});

test('change password rejects incorrect current password', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();

    $this->actingAs($owner);

    Livewire::test(ChangePassword::class)
        ->set('currentPassword', 'wrong-password')
        ->set('password', STRONG_PASSWORD)
        ->set('password_confirmation', STRONG_PASSWORD)
        ->call('updatePassword')
        ->assertHasErrors(['currentPassword']);
});

test('change password rejects weak new password', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();

    $this->actingAs($owner);

    Livewire::test(ChangePassword::class)
        ->set('currentPassword', env('SEED_OWNER_PASSWORD', 'password'))
        ->set('password', 'short')
        ->set('password_confirmation', 'short')
        ->call('updatePassword')
        ->assertHasErrors(['password']);
});

test('change password rejects reusing the current password', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();

    $this->actingAs($owner);

    Livewire::test(ChangePassword::class)
        ->set('currentPassword', env('SEED_OWNER_PASSWORD', 'password'))
        ->set('password', env('SEED_OWNER_PASSWORD', 'password'))
        ->set('password_confirmation', env('SEED_OWNER_PASSWORD', 'password'))
        ->call('updatePassword')
        ->assertHasErrors(['password']);
});
