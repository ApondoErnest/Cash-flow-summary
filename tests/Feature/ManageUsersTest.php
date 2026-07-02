<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Centers\Models\Organization;
use App\Modules\Users\Livewire\ManageUserForm;
use App\Modules\Users\Livewire\ManageUsers;
use App\Support\Auth\RoleName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('owner can view manage users without an active center', function () {
    actingAsOwnerWithoutActiveCenter();

    $this->get(route('users.index'))
        ->assertOk()
        ->assertSee(__('user.manage.title'), false)
        ->assertSee(__('user.manage.create'), false);
});

test('owner manage users list supports filters by center role and status', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $centerA = createTestCenter($owner->organization, ['name' => 'Center A']);
    $centerB = createTestCenter($owner->organization, ['name' => 'Center B']);

    Role::findOrCreate(RoleName::CenterManager, 'web');
    Role::findOrCreate(RoleName::Cashier, 'web');

    $manager = User::query()->create([
        'organization_id' => $owner->organization_id,
        'center_id' => $centerA->id,
        'name' => 'Manager Alpha',
        'username' => 'manager-alpha',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $manager->assignRole(RoleName::CenterManager);

    $cashier = User::query()->create([
        'organization_id' => $owner->organization_id,
        'center_id' => $centerB->id,
        'name' => 'Cashier Beta',
        'username' => 'cashier-beta',
        'password' => bcrypt('password'),
        'is_active' => false,
    ]);
    $cashier->assignRole(RoleName::Cashier);

    Livewire::test(ManageUsers::class)
        ->set('roleFilter', RoleName::Cashier)
        ->assertSee('Cashier Beta', false)
        ->assertDontSee('Manager Alpha', false);

    Livewire::test(ManageUsers::class)
        ->set('centerFilter', (string) $centerA->id)
        ->assertSee('Manager Alpha', false)
        ->assertDontSee('Cashier Beta', false);
});

test('owner can create staff user with temporary password', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization);

    Livewire::test(ManageUserForm::class)
        ->set('name', 'New Manager')
        ->set('username', 'new-manager')
        ->set('role', RoleName::CenterManager)
        ->set('centerId', $center->id)
        ->call('save')
        ->assertRedirect(route('users.index'));

    $created = User::query()->where('username', 'new-manager')->first();

    expect($created)->not->toBeNull();
    expect($created->center_id)->toBe($center->id);
    expect($created->must_change_password)->toBeTrue();
    expect($created->hasRole(RoleName::CenterManager))->toBeTrue();
});

test('owner can reassign center and deactivate staff user', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $centerA = createTestCenter($owner->organization, ['name' => 'Center A']);
    $centerB = createTestCenter($owner->organization, ['name' => 'Center B']);

    Role::findOrCreate(RoleName::Cashier, 'web');

    $user = User::query()->create([
        'organization_id' => $owner->organization_id,
        'center_id' => $centerA->id,
        'name' => 'Staff User',
        'username' => 'staff-user',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $user->assignRole(RoleName::Cashier);

    Livewire::test(ManageUserForm::class, ['user' => $user])
        ->set('centerId', $centerB->id)
        ->set('is_active', false)
        ->call('save')
        ->assertRedirect(route('users.index'));

    $user->refresh();

    expect($user->center_id)->toBe($centerB->id);
    expect($user->is_active)->toBeFalse();
});

test('owner can reset staff password to temporary password', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization);

    Role::findOrCreate(RoleName::Cashier, 'web');

    $user = User::query()->create([
        'organization_id' => $owner->organization_id,
        'center_id' => $center->id,
        'name' => 'Reset Target',
        'username' => 'reset-target',
        'password' => bcrypt('password'),
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $user->assignRole(RoleName::Cashier);

    Livewire::test(ManageUsers::class)
        ->call('resetPassword', $user->id)
        ->assertSet('temporaryPasswordUsername', 'reset-target');

    expect($user->fresh()->must_change_password)->toBeTrue();
});

test('owner cannot reset their own password from manage users', function () {
    $owner = actingAsOwnerWithoutActiveCenter();

    Livewire::test(ManageUsers::class)
        ->call('resetPassword', $owner->id)
        ->assertForbidden();
});

test('staff cannot access manage users pages', function () {
    actingAsManager();

    $this->get(route('users.index'))->assertForbidden();
    $this->get(route('users.create'))->assertForbidden();
});

test('owner cannot edit user from another organization', function () {
    actingAsOwnerWithoutActiveCenter();

    $foreignOrganization = Organization::query()->create([
        'name' => 'Foreign Organization',
        'code' => 'FRN-'.uniqid(),
    ]);
    $foreignCenter = createTestCenter($foreignOrganization);

    Role::findOrCreate(RoleName::Cashier, 'web');

    $foreignUser = User::query()->create([
        'organization_id' => $foreignOrganization->id,
        'center_id' => $foreignCenter->id,
        'name' => 'Foreign User',
        'username' => 'foreign-user',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $foreignUser->assignRole(RoleName::Cashier);

    $this->get(route('users.edit', $foreignUser))->assertForbidden();
});

test('username must be unique when creating users', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization);

    Livewire::test(ManageUserForm::class)
        ->set('name', 'Duplicate User')
        ->set('username', $owner->username)
        ->set('role', RoleName::CenterManager)
        ->set('centerId', $center->id)
        ->call('save')
        ->assertHasErrors(['username']);
});
