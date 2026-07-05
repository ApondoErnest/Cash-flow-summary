<?php

declare(strict_types=1);

use App\Modules\AuditLogging\Livewire\AuditLogList;
use App\Modules\AuditLogging\Services\AuditLogService;
use App\Modules\Centers\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('owner can view audit logs without an active center', function () {
    actingAsOwnerWithoutActiveCenter();

    $this->get(route('audit-logs.index'))
        ->assertOk()
        ->assertSee(__('audit.list.title'), false);
});

test('owner audit log list shows organization-wide events across centers', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $centerA = createTestCenter($owner->organization, ['name' => 'Center Alpha']);
    $centerB = createTestCenter($owner->organization, ['name' => 'Center Beta']);

    createAuditLog($centerA->id, 'center.alpha.event', $owner);
    createAuditLog($centerB->id, 'center.beta.event', $owner);

    Livewire::test(AuditLogList::class)
        ->assertSee('center.alpha.event', false)
        ->assertSee('center.beta.event', false)
        ->assertSee('Center Alpha', false)
        ->assertSee('Center Beta', false);
});

test('owner audit log list can filter by center', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $centerA = createTestCenter($owner->organization, ['name' => 'Filter Center A']);
    $centerB = createTestCenter($owner->organization, ['name' => 'Filter Center B']);

    createAuditLog($centerA->id, 'filter.center.a', $owner);
    createAuditLog($centerB->id, 'filter.center.b', $owner);

    $component = Livewire::test(AuditLogList::class)
        ->set('centerFilter', (string) $centerA->id);

    $events = collect($component->logs->items())->pluck('event');

    expect($events)->toContain('filter.center.a')
        ->and($events)->not->toContain('filter.center.b');
});

test('owner audit log list can filter by event', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization);

    createAuditLog($center->id, 'verification.rejected', $owner);
    createAuditLog($center->id, 'user.password_reset', $owner);

    $component = Livewire::test(AuditLogList::class)
        ->set('eventFilter', 'verification.rejected');

    $events = collect($component->logs->items())->pluck('event');

    expect($events)->toContain('verification.rejected')
        ->and($events)->not->toContain('user.password_reset');
});

test('owner can view audit log detail for organization event', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization);

    $log = createAuditLog($center->id, 'verification.rejected', $owner, [
        'resource_type' => 'ImportVerification',
        'resource_id' => 42,
        'new_values' => ['filename' => 'report.csv'],
        'reason' => 'Invalid footer',
    ]);

    Livewire::test(AuditLogList::class)
        ->call('selectLog', $log->id)
        ->assertSet('selectedLogId', $log->id)
        ->assertSee('Invalid footer', false)
        ->assertSee('report.csv', false);
});

test('owner audit log list excludes foreign organization events', function () {
    actingAsOwnerWithoutActiveCenter();

    $foreignOrganization = Organization::query()->create([
        'name' => 'Foreign Organization',
        'code' => 'FRN-'.uniqid(),
    ]);
    $foreignCenter = createTestCenter($foreignOrganization);

    createAuditLog($foreignCenter->id, 'foreign.event');

    $events = collect(Livewire::test(AuditLogList::class)->logs->items())->pluck('event');

    expect($events)->not->toContain('foreign.event');
});

test('staff cannot access audit log list', function () {
    actingAsManager();

    $this->get(route('audit-logs.index'))->assertForbidden();
});

test('cashier cannot access audit log list', function () {
    actingAsCashier();

    $this->get(route('audit-logs.index'))->assertForbidden();
});

test('audit log service labels known events', function () {
    $service = app(AuditLogService::class);

    expect($service->eventLabel('verification.rejected'))
        ->toBe(__('audit.events.verification_rejected'))
        ->and($service->eventLabel('custom.event'))
        ->toBe('custom.event');
});
