<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Support\Navigation\RoleNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner navigation includes operational and administrative sections', function () {
    actingAsOwner();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Operations', false);
    $response->assertSee('Administration', false);
    $response->assertSee('Daily Versions', false);
    $response->assertSee('WhatsApp History', false);
    $response->assertSee('Manage Centers', false);
    $response->assertSee('Organization Settings', false);
    $response->assertSee('Active center', false);
    $response->assertSee('Switch center', false);
});

test('manager navigation shows operational items only', function () {
    actingAsOwner();

    $response = $this->get('/?role=manager');

    $response->assertOk();
    $response->assertSee('Import CSV', false);
    $response->assertSee('Records', false);
    $response->assertSee('Reports', false);
    $response->assertSee('Assigned center', false);
    $response->assertDontSee('Administration', false);
    $response->assertDontSee('Manage Centers', false);
    $response->assertDontSee('Daily Versions', false);
    $response->assertDontSee('Switch center', false);
});

test('cashier navigation is compact', function () {
    actingAsOwner();

    $response = $this->get('/?role=cashier');

    $response->assertOk();
    $response->assertSee(route('dashboard'), false);
    $response->assertSee(route('imports.create'), false);
    $response->assertSee(route('imports.index'), false);
    $response->assertDontSee(route('records.index'), false);
    $response->assertDontSee(route('reports.index'), false);
    $response->assertDontSee(route('revisions.index'), false);
    $response->assertDontSee(route('centers.index'), false);
});

test('role navigation registry matches ux overview item counts', function () {
    expect(RoleNavigation::groupsFor(UserRole::Owner))
        ->toHaveCount(2)
        ->and(collect(RoleNavigation::groupsFor(UserRole::Owner))->flatMap->items)
        ->toHaveCount(15);

    expect(collect(RoleNavigation::groupsFor(UserRole::Manager))->flatMap->items)
        ->toHaveCount(6);

    expect(collect(RoleNavigation::groupsFor(UserRole::Cashier))->flatMap->items)
        ->toHaveCount(3);
});

test('placeholder navigation routes render', function () {
    actingAsOwner();

    $this->get(route('imports.create'))->assertOk()->assertSee('Import CSV');
    $this->get(route('centers.index'))->assertOk()->assertSee('Manage Centers');
});
