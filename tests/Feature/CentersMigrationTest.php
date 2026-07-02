<?php

declare(strict_types=1);

use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\CenterCalendarException;
use App\Modules\Centers\Models\CenterOperatingCalendar;
use App\Modules\Centers\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('centers migration creates tables with data model columns', function () {
    expect(Schema::hasTable('centers'))->toBeTrue();
    expect(Schema::hasColumns('centers', [
        'id',
        'organization_id',
        'name',
        'code',
        'address',
        'city',
        'region',
        'phone',
        'default_language',
        'submission_deadline',
        'is_active',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasTable('center_operating_calendars'))->toBeTrue();
    expect(Schema::hasColumns('center_operating_calendars', [
        'id',
        'center_id',
        'day_of_week',
        'is_open',
        'open_time',
        'close_time',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasTable('center_calendar_exceptions'))->toBeTrue();
    expect(Schema::hasColumns('center_calendar_exceptions', [
        'id',
        'center_id',
        'exception_date',
        'type',
        'open_time',
        'close_time',
        'notes',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('center code is unique per organization', function () {
    $organization = Organization::query()->create([
        'name' => 'Demo Group',
        'code' => 'DEMO-ORG',
    ]);

    Center::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Center A',
        'code' => 'CTR-A',
    ]);

    expect(fn () => Center::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Center B duplicate code',
        'code' => 'CTR-A',
    ]))->toThrow(\Illuminate\Database\QueryException::class);

    $otherOrg = Organization::query()->create([
        'name' => 'Other Group',
        'code' => 'OTHER-ORG',
    ]);

    $center = Center::query()->create([
        'organization_id' => $otherOrg->id,
        'name' => 'Center same code different org',
        'code' => 'CTR-A',
    ]);

    expect($center->code)->toBe('CTR-A');
});

test('center operating calendar enforces one row per day of week', function () {
    $center = createCenterForCalendarTests();

    CenterOperatingCalendar::query()->create([
        'center_id' => $center->id,
        'day_of_week' => 1,
        'is_open' => true,
        'open_time' => '08:00:00',
        'close_time' => '18:00:00',
    ]);

    expect(fn () => CenterOperatingCalendar::query()->create([
        'center_id' => $center->id,
        'day_of_week' => 1,
        'is_open' => false,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('center calendar exception persists with valid type', function () {
    $center = createCenterForCalendarTests();

    $exception = CenterCalendarException::query()->create([
        'center_id' => $center->id,
        'exception_date' => '2026-12-25',
        'type' => 'holiday',
        'notes' => 'Christmas closure',
    ]);

    expect($exception->type)->toBe('holiday');
    expect($exception->center->id)->toBe($center->id);
});

function createCenterForCalendarTests(): Center
{
    $organization = Organization::query()->create([
        'name' => 'Calendar Test Org',
        'code' => 'CAL-ORG',
    ]);

    return Center::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Calendar Test Center',
        'code' => 'CAL-CTR',
    ]);
}
