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

test('login page shows verified cash shield brand mark and favicon links', function () {
    $this->seed(DatabaseSeeder::class);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('favicon.svg', false)
        ->assertSee('apple-touch-icon.png', false)
        ->assertSee('brand/verified-cash-shield.svg', false)
        ->assertSee('site.webmanifest', false);
});

test('login page shows midday finance eyebrow and organization line', function () {
    $this->seed(DatabaseSeeder::class);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('MIDDAY FINANCE', false)
        ->assertSee('Organisation:', false)
        ->assertSee('Demo Inspection Organization', false);
});

test('app shell shows verified cash shield in sidebar brand', function () {
    actingAsOwner();

    $this->get('/')
        ->assertOk()
        ->assertSee('brand/verified-cash-shield.svg', false)
        ->assertSee('favicon.svg', false);
});

test('center selection page includes favicon links', function () {
    actingAsOwnerWithoutActiveCenter();

    $this->get(route('center.select'))
        ->assertOk()
        ->assertSee('favicon.svg', false)
        ->assertSee('apple-touch-icon.png', false)
        ->assertSee('site.webmanifest', false);
});

test('verified cash shield svg assets are well formed', function () {
    foreach ([
        public_path('favicon.svg'),
        public_path('brand/verified-cash-shield.svg'),
    ] as $path) {
        expect(file_exists($path))->toBeTrue();

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $loaded = $document->load($path);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        expect($loaded)->toBeTrue()
            ->and($errors)->toBe([]);
    }
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
