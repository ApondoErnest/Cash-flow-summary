<?php

declare(strict_types=1);

use App\Support\Branding\OrganizationBranding;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('organization branding resolves active organization name', function () {
    $this->seed(DatabaseSeeder::class);

    expect(app(OrganizationBranding::class)->displayName())
        ->toBe('Demo Inspection Organization');
});

test('organization branding returns empty string when no organization exists', function () {
    expect(app(OrganizationBranding::class)->displayName())
        ->toBe('');
});

test('login page shows midday finance eyebrow and organization line', function () {
    $this->seed(DatabaseSeeder::class);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('MIDDAY FINANCE', false)
        ->assertSee('Organisation:', false)
        ->assertSee('Demo Inspection Organization', false);
});

test('login page organization line reflects updated organization profile name', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $owner->organization->update(['name' => 'NACHO Inspection Group']);

    auth()->logout();

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('MIDDAY FINANCE', false)
        ->assertSee('Organisation:', false)
        ->assertSee('NACHO Inspection Group', false);
});
