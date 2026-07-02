<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Centers\Models\Organization;
use App\Support\Auth\RoleName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('spatie permission migration creates role and permission tables', function () {
    expect(Schema::hasTable('roles'))->toBeTrue();
    expect(Schema::hasTable('permissions'))->toBeTrue();
    expect(Schema::hasTable('model_has_roles'))->toBeTrue();
    expect(Schema::hasTable('model_has_permissions'))->toBeTrue();
    expect(Schema::hasTable('role_has_permissions'))->toBeTrue();
});

test('user model can assign and check spatie roles', function () {
    $organization = Organization::query()->create([
        'name' => 'Role Test Org',
        'code' => 'ROLE-ORG',
    ]);

    $user = User::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Owner User',
        'username' => 'owner.roles',
        'password' => 'secret-password',
    ]);

    Role::create(['name' => RoleName::Owner, 'guard_name' => 'web']);

    $user->assignRole(RoleName::Owner);

    expect($user->hasRole(RoleName::Owner))->toBeTrue();
    expect($user->hasRole(RoleName::CenterManager))->toBeFalse();
});

test('roles and permissions can be linked', function () {
    $role = Role::create(['name' => RoleName::Cashier, 'guard_name' => 'web']);
    $permission = Permission::create(['name' => 'imports.upload', 'guard_name' => 'web']);

    $role->givePermissionTo($permission);

    expect($role->hasPermissionTo('imports.upload'))->toBeTrue();
});

test('role name constants match data model seed names', function () {
    expect(RoleName::all())->toBe([
        'owner',
        'center_manager',
        'cashier',
    ]);
});

test('navigation user role enum maps to spatie role names', function () {
    expect(\App\Enums\UserRole::Owner->spatieName())->toBe('owner');
    expect(\App\Enums\UserRole::Manager->spatieName())->toBe('center_manager');
    expect(\App\Enums\UserRole::Cashier->spatieName())->toBe('cashier');
});

test('permission migration runs after users table', function () {
    $migrationFiles = collect(scandir(database_path('migrations')))
        ->filter(fn (string $file) => str_ends_with($file, '.php'))
        ->values();

    $permissionsIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100003_create_permission_tables'));
    $usersIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100002_create_users_table'));

    expect($permissionsIndex)->toBeGreaterThan($usersIndex);
});
