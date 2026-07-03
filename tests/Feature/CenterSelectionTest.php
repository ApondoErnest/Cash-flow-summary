<?php

declare(strict_types=1);

use App\Modules\Centers\Livewire\CenterSelection;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\Organization;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\Centers\Services\CenterSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('owner center selection page renders searchable center list', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization, [
        'name' => 'NACHO Yaounde',
        'code' => 'YDE-MENDONG',
        'city' => 'Yaounde',
    ]);

    $this->get(route('center.select'))
        ->assertOk()
        ->assertSee(__('center.selection.title'), false)
        ->assertSee(__('center.selection.description'), false)
        ->assertSee('NACHO Yaounde', false)
        ->assertSee('YDE-MENDONG', false)
        ->assertSee('Yaounde', false)
        ->assertSee(__('center.selection.open_center'), false);
});

test('center selection service lists only active centers in owner organization', function () {
    $owner = actingAsOwnerWithoutActiveCenter();

    Center::query()
        ->where('organization_id', $owner->organization_id)
        ->update(['is_active' => false]);

    $active = createTestCenter($owner->organization, ['name' => 'Active Center', 'is_active' => true]);
    createTestCenter($owner->organization, ['name' => 'Inactive Center', 'is_active' => false]);
    $otherOrganization = Organization::query()->create([
        'name' => 'Other Organization',
        'code' => 'OTH-'.uniqid(),
    ]);
    createTestCenter($otherOrganization, ['name' => 'Other Org Center']);

    $centers = app(CenterSelectionService::class)->activeCentersFor($owner);

    expect($centers)->toHaveCount(1);
    expect($centers->first()?->is($active))->toBeTrue();
});

test('center selection service filters centers by search query', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    createTestCenter($owner->organization, ['name' => 'NACHO Yaounde', 'code' => 'YDE-MENDONG', 'city' => 'Yaounde']);
    createTestCenter($owner->organization, ['name' => 'Douala Hub', 'code' => 'DLA-01', 'city' => 'Douala']);

    $service = app(CenterSelectionService::class);
    $allCenters = $service->activeCentersFor($owner);

    expect($service->searchCenters($allCenters, 'douala')->pluck('name')->all())->toBe(['Douala Hub']);
});

test('owner can open selected center and reach intended operational route', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization, [
        'name' => 'NACHO Yaounde',
        'code' => 'YDE-MENDONG',
    ]);

    $this->get(route('imports.index'));

    Livewire::test(CenterSelection::class)
        ->set('centerId', $center->id)
        ->call('openCenter')
        ->assertRedirect(route('imports.index'));

    $context = app(ActiveCenterContextService::class)->resolve($owner);

    expect($context?->centerId)->toBe($center->id);
    expect($context?->centerName)->toBe('NACHO Yaounde');
});

test('owner center selection shows empty state when no active centers exist', function () {
    actingAsOwnerWithoutActiveCenter();

    Center::query()->update(['is_active' => false]);

    $this->get(route('center.select'))
        ->assertOk()
        ->assertSee(__('center.selection.empty_title'), false)
        ->assertSee(__('center.selection.create_center'), false)
        ->assertDontSee(__('center.selection.open_center'), false);
});

test('owner center selection shows cleared active center status message', function () {
    actingAsOwnerWithoutActiveCenter();

    $this->withSession(['status' => __('center.active_center_cleared')])
        ->get(route('center.select'))
        ->assertSee(__('center.active_center_cleared'), false);
});

test('staff cannot access owner center selection page', function () {
    actingAsManager();

    $this->get(route('center.select'))->assertForbidden();

    actingAsCashier();

    $this->get(route('center.select'))->assertForbidden();
});

test('center selection preselects the only active center', function () {
    $owner = actingAsOwnerWithoutActiveCenter();

    Center::query()
        ->where('organization_id', $owner->organization_id)
        ->update(['is_active' => false]);

    $center = createTestCenter($owner->organization, ['name' => 'Only Center']);

    Livewire::test(CenterSelection::class)
        ->assertSet('centerId', $center->id);
});

test('center selection shows back to dashboard when owner already has active center', function () {
    actingAsOwner();

    $this->get(route('center.select'))
        ->assertOk()
        ->assertSee(__('center.selection.back_to_dashboard'), false)
        ->assertSee(route('dashboard'), false);
});

test('center selection hides back to dashboard when owner has no active center', function () {
    actingAsOwnerWithoutActiveCenter();

    $this->get(route('center.select'))
        ->assertOk()
        ->assertDontSee(__('center.selection.back_to_dashboard'), false);
});
