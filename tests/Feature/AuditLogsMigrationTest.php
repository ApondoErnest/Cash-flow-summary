<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('audit logs migration creates table with data model columns', function () {
    expect(Schema::hasTable('audit_logs'))->toBeTrue();
    expect(Schema::hasColumns('audit_logs', [
        'id',
        'user_id',
        'center_id',
        'event',
        'resource_type',
        'resource_id',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
        'user_agent',
        'created_at',
    ]))->toBeTrue();

    expect(Schema::hasColumn('audit_logs', 'updated_at'))->toBeFalse();
});

test('audit log persists immutable event record', function () {
    $organization = Organization::query()->create([
        'name' => 'Audit Org',
        'code' => 'AUD-ORG',
    ]);

    $center = Center::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Audit Center',
        'code' => 'AUD-CTR',
    ]);

    $user = User::query()->create([
        'organization_id' => $organization->id,
        'center_id' => $center->id,
        'name' => 'Auditor',
        'username' => 'auditor.user',
        'password' => 'secret-password',
    ]);

    $log = AuditLog::query()->create([
        'user_id' => $user->id,
        'center_id' => $center->id,
        'event' => 'center.updated',
        'resource_type' => Center::class,
        'resource_id' => $center->id,
        'old_values' => ['is_active' => true],
        'new_values' => ['is_active' => false],
        'reason' => 'Seasonal closure',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest/Feature',
    ]);

    expect($log->user->username)->toBe('auditor.user');
    expect($log->center->code)->toBe('AUD-CTR');
    expect($log->old_values)->toBe(['is_active' => true]);
    expect($log->fresh()->updated_at)->toBeNull();
});

test('audit log allows system events without user or center', function () {
    $log = AuditLog::query()->create([
        'event' => 'verification.rejected',
        'new_values' => ['token' => 'abc-123', 'filename' => 'import.csv'],
    ]);

    expect($log->user_id)->toBeNull();
    expect($log->center_id)->toBeNull();
    expect($log->event)->toBe('verification.rejected');
});

test('audit logs migration runs after users and centers', function () {
    $migrationFiles = collect(scandir(database_path('migrations')))
        ->filter(fn (string $file) => str_ends_with($file, '.php'))
        ->values();

    $auditIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100004_create_audit_logs_table'));
    $usersIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100002_create_users_table'));
    $centersIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100001_create_centers_and_calendars'));

    expect($auditIndex)->toBeGreaterThan($usersIndex);
    expect($auditIndex)->toBeGreaterThan($centersIndex);
});
