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
    $response->assertSee('mf-header-center--switchable', false);
});

test('manager navigation shows operational items only', function () {
    actingAsManager();

    $response = $this->get(route('dashboard'));

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
    actingAsCashier();

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee(route('dashboard'), false);
    $response->assertSee(route('imports.create'), false);
    $response->assertSee(route('imports.index'), false);
    $response->assertDontSee(route('records.index'), false);
    $response->assertDontSee(route('reports.index'), false);
    $response->assertDontSee(route('revisions.index'), false);
    $response->assertDontSee(route('centers.index'), false);
});

test('owner navigation ignores legacy role preview query parameter', function () {
    actingAsOwner();

    $this->get('/?role=manager')
        ->assertOk()
        ->assertSee('Administration', false)
        ->assertSee('Manage Centers', false)
        ->assertSee('Organization Settings', false)
        ->assertSee('Active center', false);
});

test('administration sidebar links use full page loads not wire navigate', function () {
    actingAsOwnerWithoutActiveCenter();

    $html = $this->get(route('centers.index'))->getContent();

    expect($html)->toContain(route('users.index'))
        ->and($html)->toContain(route('settings.organization'));

    $sidebar = file_get_contents(resource_path('views/components/navigation/sidebar.blade.php'));

    expect($sidebar)->toContain('$item->spaNavigate')
        ->and($sidebar)->not->toContain("'settings.*'");
});

test('organization and whatsapp settings sidebar items highlight independently', function () {
    actingAsOwnerWithoutActiveCenter();

    $organizationHtml = $this->get(route('settings.organization'))->getContent();
    $whatsappHtml = $this->get(route('settings.whatsapp'))->getContent();

    $organizationHref = route('settings.organization');
    $whatsappHref = route('settings.whatsapp');

    expect(substr_count($organizationHtml, $organizationHref.'" data-current'))
        ->toBe(1)
        ->and(substr_count($organizationHtml, $whatsappHref.'" data-current'))
        ->toBe(0);

    expect(substr_count($whatsappHtml, $whatsappHref.'" data-current'))
        ->toBe(1)
        ->and(substr_count($whatsappHtml, $organizationHref.'" data-current'))
        ->toBe(0);
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

    $this->get(route('imports.create'))->assertOk()->assertSee(__('csv_verification.card.heading'), false);
    $this->get(route('centers.index'))->assertOk()->assertSee('Manage Centers');
});
