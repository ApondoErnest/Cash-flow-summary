<?php

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\Organization;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\Centers\Services\OwnerPreferredCenterService;
use App\Support\Auth\RoleName;
use App\Support\Center\ActiveCenterContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsOwner(?Center $activeCenter = null): User
{
    test()->seed(DatabaseSeeder::class);

    $user = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();

    $user->forceFill(['must_change_password' => false])->save();

    test()->actingAs($user);

    setOwnerActiveCenter($user, $activeCenter);

    return $user;
}

function actingAsOwnerWithoutActiveCenter(): User
{
    $user = actingAsOwner();

    app(ActiveCenterContextService::class)->clear();

    return $user;
}

function setOwnerActiveCenter(User $user, ?Center $center = null): ActiveCenterContext
{
    $center ??= createTestCenter($user->organization);

    return app(ActiveCenterContextService::class)->set($user, $center);
}

function setOwnerPreferredCenter(User $user, ?Center $center = null): void
{
    $center ??= createTestCenter($user->organization);

    app(OwnerPreferredCenterService::class)->setPreferred($user, $center);
}

function createTestCenter(?Organization $organization = null, array $attributes = []): Center
{
    if ($organization === null) {
        $organization = Organization::query()->first()
            ?? Organization::query()->create([
                'name' => 'Test Organization',
                'code' => 'TST-ORG',
            ]);
    }

    return Center::query()->create(array_merge([
        'organization_id' => $organization->id,
        'name' => 'Test Center',
        'code' => 'TST-'.uniqid(),
        'is_active' => true,
    ], $attributes));
}

function actingAsManager(?Center $center = null): User
{
    test()->seed(DatabaseSeeder::class);

    Role::findOrCreate(RoleName::CenterManager, 'web');

    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    $center ??= createTestCenter($owner->organization);

    $manager = User::query()->create([
        'organization_id' => $center->organization_id,
        'center_id' => $center->id,
        'name' => 'Test Manager',
        'username' => 'manager-'.uniqid(),
        'password' => bcrypt('password'),
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $manager->assignRole(RoleName::CenterManager);

    test()->actingAs($manager);

    return $manager;
}

function actingAsCashier(?Center $center = null): User
{
    test()->seed(DatabaseSeeder::class);

    Role::findOrCreate(RoleName::Cashier, 'web');

    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    $center ??= createTestCenter($owner->organization);

    $cashier = User::query()->create([
        'organization_id' => $center->organization_id,
        'center_id' => $center->id,
        'name' => 'Test Cashier',
        'username' => 'cashier-'.uniqid(),
        'password' => bcrypt('password'),
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $cashier->assignRole(RoleName::Cashier);

    test()->actingAs($cashier);

    return $cashier;
}
