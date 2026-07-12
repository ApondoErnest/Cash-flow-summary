<?php

declare(strict_types=1);

use App\Modules\Centers\Livewire\ManageCenterForm;
use App\Modules\Centers\Livewire\ManageCenters;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\Organization;
use App\Modules\Centers\Services\ActiveCenterContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('owner can view manage centers list without an active center', function () {
    actingAsOwnerWithoutActiveCenter();

    $this->get(route('centers.index'))
        ->assertOk()
        ->assertSee(__('center.manage.title'), false)
        ->assertSee(__('center.manage.create'), false);
});

test('owner manage centers list shows operational metadata without financial totals', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization, [
        'name' => 'NACHO Yaounde',
        'code' => 'YDE-MENDONG',
        'city' => 'Yaounde',
        'region' => 'Centre',
    ]);

    actingAsManager($center);
    $this->actingAs($owner);

    $this->get(route('centers.index'))
        ->assertOk()
        ->assertSee('NACHO Yaounde', false)
        ->assertSee('YDE-MENDONG', false)
        ->assertSee('Yaounde', false)
        ->assertSee('1', false)
        ->assertSee(__('center.manage.actions.open_center'), false)
        ->assertDontSee('TTC', false)
        ->assertDontSee('Total', false);
});

test('manage centers css keeps primary open center button text visible', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('.mf-table-panel [data-flux-cell] [data-flux-button].mf-btn-primary')
        ->and($css)->toContain('color: var(--color-accent-foreground) !important')
        ->and($css)->toContain('[data-flux-button].bg-transparent');
});

test('staff cannot access manage centers pages', function () {
    actingAsManager();

    $this->get(route('centers.index'))->assertForbidden();
    $this->get(route('centers.create'))->assertForbidden();
});

test('owner can create a center and set it as login default', function () {
    $owner = actingAsOwnerWithoutActiveCenter();

    Livewire::test(ManageCenterForm::class)
        ->assertSet('whatsapp_summary_time', '18:00')
        ->set('name', 'Douala Hub')
        ->set('code', 'DLA-01')
        ->set('city', 'Douala')
        ->set('default_language', 'fr')
        ->set('setAsDefault', true)
        ->call('save')
        ->assertRedirect(route('centers.index'));

    $center = Center::query()->where('code', 'DLA-01')->first();

    expect($center)->not->toBeNull();
    expect($center->name)->toBe('Douala Hub');
    expect(substr((string) $center->whatsapp_summary_time, 0, 5))->toBe('18:00');
    expect($owner->fresh()->preferred_center_id)->toBe($center->id);
});

test('owner can set an explicit whatsapp summary time on a center', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization, [
        'name' => 'Timed Center',
        'code' => 'TIME-01',
        'whatsapp_summary_time' => null,
    ]);

    Livewire::test(ManageCenterForm::class, ['center' => $center])
        ->assertSet('whatsapp_summary_time', '18:00')
        ->set('whatsapp_summary_time', '19:30')
        ->call('save')
        ->assertRedirect(route('centers.index'));

    expect(substr((string) $center->fresh()->whatsapp_summary_time, 0, 5))->toBe('19:30')
        ->and($center->fresh()->resolvedWhatsappSummaryTime())->toBe('19:30');
});

test('first center form defaults set as default checkbox to checked', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    Center::query()->where('organization_id', $owner->organization_id)->delete();

    Livewire::test(ManageCenterForm::class)
        ->assertSet('setAsDefault', true);
});

test('owner can edit and deactivate a center', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization, [
        'name' => 'Active Center',
        'code' => 'ACT-01',
        'is_active' => true,
    ]);

    Livewire::test(ManageCenterForm::class, ['center' => $center])
        ->set('name', 'Renamed Center')
        ->set('is_active', false)
        ->call('save')
        ->assertRedirect(route('centers.index'));

    $center->refresh();

    expect($center->name)->toBe('Renamed Center');
    expect($center->is_active)->toBeFalse();
});

test('center code must be unique within organization', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    createTestCenter($owner->organization, ['code' => 'DUP-01']);

    Livewire::test(ManageCenterForm::class)
        ->set('name', 'Duplicate Code Center')
        ->set('code', 'DUP-01')
        ->call('save')
        ->assertHasErrors(['code']);
});

test('open center action switches active center and redirects to dashboard', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization, ['name' => 'Target Center']);

    Livewire::test(ManageCenters::class)
        ->call('openCenter', $center->id)
        ->assertRedirect(route('dashboard'));

    expect(app(ActiveCenterContextService::class)->resolve($owner)?->centerId)->toBe($center->id);
});

test('open center is hidden for inactive centers in the list view', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    Center::query()->where('organization_id', $owner->organization_id)->delete();
    createTestCenter($owner->organization, [
        'name' => 'Inactive Center',
        'code' => 'OFF-01',
        'is_active' => false,
    ]);

    $this->actingAs($owner)
        ->get(route('centers.index'))
        ->assertOk()
        ->assertSee('Inactive Center', false)
        ->assertDontSee('wire:click="openCenter', false);
});

test('owner cannot edit center from another organization', function () {
    actingAsOwnerWithoutActiveCenter();
    $foreignOrganization = Organization::query()->create([
        'name' => 'Foreign Organization',
        'code' => 'FRN-'.uniqid(),
    ]);
    $foreignCenter = createTestCenter($foreignOrganization);

    $this->get(route('centers.edit', $foreignCenter))->assertForbidden();
});
