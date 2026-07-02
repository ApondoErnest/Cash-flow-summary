<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('logout control is present in app shell header on center selection', function () {
    actingAsOwnerWithoutActiveCenter();

    $this->get(route('center.select'))
        ->assertOk()
        ->assertSee(route('logout'), false)
        ->assertSee(__('auth.logout'), false);
});

test('sidebar user menu exposes logout for owner', function () {
    actingAsOwner();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-flux-sidebar-profile', false)
        ->assertSee(__('auth.logout'), false);
});

test('guest auth pages expose logout while authenticated', function () {
    $this->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = \App\Models\User::query()
        ->where('username', env('SEED_OWNER_USERNAME', 'owner'))
        ->firstOrFail();

    $this->actingAs($owner);

    $this->get(route('password.change'))
        ->assertOk()
        ->assertSee(__('auth.logout'), false);
});
