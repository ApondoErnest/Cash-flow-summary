<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('users migration creates table with data model columns', function () {
    expect(Schema::hasTable('users'))->toBeTrue();
    expect(Schema::hasColumns('users', [
        'id',
        'organization_id',
        'center_id',
        'name',
        'username',
        'phone',
        'email',
        'password',
        'is_active',
        'must_change_password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'last_login_at',
        'remember_token',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasColumn('users', 'email_verified_at'))->toBeFalse();
});

test('user persists with username login fields and organization binding', function () {
    $organization = Organization::query()->create([
        'name' => 'Demo Group',
        'code' => 'USER-ORG',
    ]);

    $center = Center::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Main Center',
        'code' => 'MAIN',
    ]);

    $owner = User::query()->create([
        'organization_id' => $organization->id,
        'center_id' => null,
        'name' => 'Owner User',
        'username' => 'owner.demo',
        'password' => 'secret-password',
    ]);

    $manager = User::query()->create([
        'organization_id' => $organization->id,
        'center_id' => $center->id,
        'name' => 'Manager User',
        'username' => 'manager.demo',
        'password' => 'secret-password',
        'must_change_password' => true,
    ]);

    expect($owner->center_id)->toBeNull();
    expect($owner->organization->code)->toBe('USER-ORG');
    expect($manager->center->code)->toBe('MAIN');
    expect($manager->must_change_password)->toBeTrue();
});

test('username must be unique', function () {
    $organization = Organization::query()->create([
        'name' => 'Unique Org',
        'code' => 'UNQ-ORG',
    ]);

    User::query()->create([
        'organization_id' => $organization->id,
        'name' => 'First User',
        'username' => 'duplicate.user',
        'password' => 'secret-password',
    ]);

    expect(fn () => User::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Second User',
        'username' => 'duplicate.user',
        'password' => 'secret-password',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('users migration runs after organizations and centers', function () {
    $migrationFiles = collect(scandir(database_path('migrations')))
        ->filter(fn (string $file) => str_ends_with($file, '.php'))
        ->values();

    $usersIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100002_create_users_table'));
    $organizationsIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100000_create_organizations_table'));
    $centersIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100001_create_centers_and_calendars'));

    expect($usersIndex)->toBeGreaterThan($organizationsIndex);
    expect($usersIndex)->toBeGreaterThan($centersIndex);
});
