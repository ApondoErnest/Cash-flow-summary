<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Centers\Models\Organization;
use App\Support\Auth\RoleName;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('wave 1 seed creates spatie roles', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    expect(Role::query()->pluck('name')->sort()->values()->all())->toBe([
        RoleName::Cashier,
        RoleName::CenterManager,
        RoleName::Owner,
    ]);
});

test('wave 1 seed creates owner account with null center', function () {
    $this->seed(DatabaseSeeder::class);

    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->first();

    expect($owner)->not->toBeNull();
    expect($owner->center_id)->toBeNull();
    expect($owner->must_change_password)->toBeTrue();
    expect($owner->hasRole(RoleName::Owner))->toBeTrue();
    expect($owner->organization->code)->toBe('DEMO');
});

test('database seeder is idempotent', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Role::query()->count())->toBe(3);
    expect(User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->count())->toBe(1);
    expect(Organization::query()->where('code', 'DEMO')->count())->toBe(1);
});

test('re-seeding does not reset an existing owner password', function () {
    $this->seed(DatabaseSeeder::class);

    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    $owner->forceFill([
        'password' => 'CashflowAdmin2026!',
        'must_change_password' => false,
    ])->save();

    $this->seed(DatabaseSeeder::class);

    $owner->refresh();

    expect($owner->must_change_password)->toBeFalse();
    expect(\Illuminate\Support\Facades\Hash::check('CashflowAdmin2026!', $owner->password))->toBeTrue();
});
