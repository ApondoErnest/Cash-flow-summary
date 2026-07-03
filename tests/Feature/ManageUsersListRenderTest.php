<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Auth\RoleName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('manage users list html includes visible filter controls and row actions', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization, ['name' => 'Cente1']);

    Role::findOrCreate(RoleName::CenterManager, 'web');

    $manager = User::query()->create([
        'organization_id' => $owner->organization_id,
        'center_id' => $center->id,
        'name' => 'Zeng Tu',
        'username' => 'zeng-tu',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $manager->assignRole(RoleName::CenterManager);

    $html = $this->get(route('users.index'))->getContent();

    expect($html)->toContain(__('user.manage.actions.edit'))
        ->and($html)->toContain(__('user.manage.filters.all_centers'))
        ->and($html)->toContain('data-flux-control')
        ->and($html)->toContain('mf-status-badge')
        ->and($html)->toContain('users/')
        ->and($html)->toContain('applyAppearance(\'light\')');
});
