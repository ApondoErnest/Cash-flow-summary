<?php

declare(strict_types=1);

use App\Enums\CalendarExceptionType;
use App\Modules\Centers\Livewire\OperatingCalendar;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\CenterCalendarException;
use App\Modules\Centers\Models\CenterOperatingCalendar;
use App\Modules\Centers\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('owner can view operating calendar for organization center', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization, ['name' => 'Calendar Center']);

    $this->get(route('centers.calendar', $center))
        ->assertOk()
        ->assertSee(__('center.calendar.weekly_title'), false)
        ->assertSee(__('center.calendar.exceptions_title'), false)
        ->assertSee('Calendar Center', false);
});

test('operating calendar initializes default weekly schedule on first visit', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization);

    expect(CenterOperatingCalendar::query()->where('center_id', $center->id)->count())->toBe(0);

    Livewire::test(OperatingCalendar::class, ['center' => $center]);

    expect(CenterOperatingCalendar::query()->where('center_id', $center->id)->count())->toBe(7);
    expect(CenterOperatingCalendar::query()->where('center_id', $center->id)->where('day_of_week', 0)->value('is_open'))->toBeFalsy();
});

test('owner can save weekly operating schedule', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization);

    Livewire::test(OperatingCalendar::class, ['center' => $center])
        ->set('weeklyDays.1.is_open', true)
        ->set('weeklyDays.1.open_time', '09:00')
        ->set('weeklyDays.1.close_time', '17:00')
        ->call('saveWeeklySchedule');

    $monday = CenterOperatingCalendar::query()
        ->where('center_id', $center->id)
        ->where('day_of_week', 1)
        ->first();

    expect($monday?->open_time)->toBe('09:00:00');
    expect($monday?->close_time)->toBe('17:00:00');
});

test('owner can add holiday and special open calendar exceptions', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization);

    Livewire::test(OperatingCalendar::class, ['center' => $center])
        ->set('exception_date', '2026-12-25')
        ->set('exception_type', CalendarExceptionType::Holiday->value)
        ->set('exception_notes', 'Christmas')
        ->call('saveException');

    Livewire::test(OperatingCalendar::class, ['center' => $center])
        ->set('exception_date', '2026-07-14')
        ->set('exception_type', CalendarExceptionType::SpecialOpen->value)
        ->set('exception_open_time', '10:00')
        ->set('exception_close_time', '14:00')
        ->set('exception_notes', 'National day catch-up')
        ->call('saveException');

    expect(CenterCalendarException::query()->where('center_id', $center->id)->count())->toBe(2);
});

test('calendar exception date must be unique per center', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization);

    CenterCalendarException::query()->create([
        'center_id' => $center->id,
        'exception_date' => '2026-12-25',
        'type' => 'holiday',
    ]);

    Livewire::test(OperatingCalendar::class, ['center' => $center])
        ->set('exception_date', '2026-12-25')
        ->set('exception_type', CalendarExceptionType::Closure->value)
        ->call('saveException')
        ->assertHasErrors(['exception_date']);
});

test('owner can edit and delete calendar exceptions', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization);

    $exception = CenterCalendarException::query()->create([
        'center_id' => $center->id,
        'exception_date' => '2026-08-15',
        'type' => 'closure',
        'notes' => 'Maintenance',
    ]);

    Livewire::test(OperatingCalendar::class, ['center' => $center])
        ->call('editException', $exception->id)
        ->set('exception_notes', 'Planned maintenance')
        ->call('saveException');

    expect($exception->fresh()->notes)->toBe('Planned maintenance');

    Livewire::test(OperatingCalendar::class, ['center' => $center])
        ->call('deleteException', $exception->id);

    expect(CenterCalendarException::query()->find($exception->id))->toBeNull();
});

test('staff cannot access operating calendar page', function () {
    actingAsManager();

    $center = Center::query()->firstOrFail();

    $this->get(route('centers.calendar', $center))->assertForbidden();
});

test('owner cannot access calendar for center outside organization', function () {
    actingAsOwnerWithoutActiveCenter();
    $foreignOrganization = Organization::query()->create([
        'name' => 'Foreign Organization',
        'code' => 'FRN-'.uniqid(),
    ]);
    $foreignCenter = createTestCenter($foreignOrganization);

    $this->get(route('centers.calendar', $foreignCenter))->assertForbidden();
});

test('manage centers list links to operating calendar', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization, ['name' => 'Linked Center']);

    $this->actingAs($owner)
        ->get(route('centers.index'))
        ->assertOk()
        ->assertSee(route('centers.calendar', $center), false)
        ->assertSee(__('center.manage.actions.calendar'), false);
});
