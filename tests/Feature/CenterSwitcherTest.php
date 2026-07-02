<?php

declare(strict_types=1);

use App\Modules\Centers\Livewire\CenterSwitcher;
use App\Modules\Centers\Models\Organization;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\Centers\Services\ActiveCenterSwitchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('owner with active center sees switchable header dropdown', function () {
    $owner = actingAsOwner();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('mf-header-center--switchable', false)
        ->assertSee(__('center.switcher.title'), false)
        ->assertSee('Test Center', false);
});

test('owner without active center sees prompt to select center in header', function () {
    actingAsOwnerWithoutActiveCenter();

    $this->get(route('centers.index'))
        ->assertOk()
        ->assertSee('mf-header-center--prompt', false)
        ->assertSee(__('navigation.shell.no_active_center'), false)
        ->assertSee(route('center.select'), false);
});

test('owner can switch active center via header dropdown', function () {
    $owner = actingAsOwner();
    $otherCenter = createTestCenter($owner->organization, [
        'name' => 'Douala Hub',
        'code' => 'DLA-01',
    ]);

    Livewire::test(CenterSwitcher::class)
        ->call('switchCenter', $otherCenter->id)
        ->assertRedirect(route('dashboard'));

    $context = app(ActiveCenterContextService::class)->resolve($owner);

    expect($context?->centerId)->toBe($otherCenter->id);
    expect($context?->centerName)->toBe('Douala Hub');
});

test('switching active center clears configured page filter session keys', function () {
    $owner = actingAsOwner();
    $otherCenter = createTestCenter($owner->organization, ['name' => 'Other Center']);

    session([
        'owner.filters.dashboard_period' => 'monthly',
        'owner.filters.dashboard_period_from' => '2026-06-01',
        'owner.filters.dashboard_period_to' => '2026-06-15',
        'owner.filters.records_search' => 'plate-123',
        'owner.filters.imports_status' => 'failed',
        'unaffected.session.key' => 'keep',
    ]);

    app(ActiveCenterSwitchService::class)->switch($owner, $otherCenter->id);

    expect(session('owner.filters.dashboard_period'))->toBeNull();
    expect(session('owner.filters.dashboard_period_from'))->toBeNull();
    expect(session('owner.filters.dashboard_period_to'))->toBeNull();
    expect(session('owner.filters.records_search'))->toBeNull();
    expect(session('owner.filters.imports_status'))->toBeNull();
    expect(session('unaffected.session.key'))->toBe('keep');
});

test('center switcher rejects centers outside owner organization', function () {
    $owner = actingAsOwner();
    $foreignOrganization = Organization::query()->create([
        'name' => 'Foreign Organization',
        'code' => 'FRN-'.uniqid(),
    ]);
    $foreignCenter = createTestCenter($foreignOrganization);

    Livewire::test(CenterSwitcher::class)
        ->call('switchCenter', $foreignCenter->id)
        ->assertForbidden();
});

test('staff do not render owner center switcher in app shell', function () {
    actingAsManager();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('mf-header-center--switchable', false)
        ->assertDontSee('mf-header-center--prompt', false)
        ->assertSee(__('navigation.shell.assigned_center'), false);
});

test('center switcher lists all active organization centers', function () {
    $owner = actingAsOwner();
    createTestCenter($owner->organization, ['name' => 'Second Center']);

    Livewire::test(CenterSwitcher::class)
        ->assertSee('Test Center')
        ->assertSee('Second Center')
        ->assertSee(__('center.switcher.open_selection'));
});
