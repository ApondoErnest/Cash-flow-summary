<?php

declare(strict_types=1);

use App\Support\Auth\RoleName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('app shell includes mobile sidebar toggle and backdrop', function () {
    actingAsOwner();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('mf-app-shell', false);
    $response->assertSee('collapsible="mobile"', false);
    $response->assertSee('data-flux-sidebar-toggle', false);
    $response->assertSee('data-flux-sidebar-backdrop', false);
    $response->assertSee('lg:hidden', false);
});

test('app shell uses compact mobile main padding classes', function () {
    actingAsOwner();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('mf-app-main', false);
    $response->assertSee('!p-4 sm:!p-6 lg:!p-8', false);
});

test('owner dashboard uses responsive page and stat card grids', function () {
    actingAsOwner();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('mf-page', false);
    $response->assertSee('grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4', false);
});

test('sidebar navigation uses wire navigate for operational links only', function () {
    $sidebar = file_get_contents(resource_path('views/components/navigation/sidebar.blade.php'));
    $js = file_get_contents(resource_path('js/mf-sidebar.js'));

    expect($sidebar)->toContain('wire:navigate');
    expect($sidebar)->toContain('$item->spaNavigate');
    expect($sidebar)->not->toContain('flux-sidebar-toggle');
    expect($js)->toContain('livewire:navigated');
    expect($js)->toContain('flux-sidebar-toggle');
});

test('administration navigation items disable spa navigate', function () {
    $adminItems = collect(App\Support\Navigation\RoleNavigation::groupsFor(App\Enums\UserRole::Owner))
        ->firstWhere('heading', 'Administration')
        ?->items ?? [];

    expect($adminItems)->not->toBeEmpty();

    foreach ($adminItems as $item) {
        expect($item->spaNavigate)->toBeFalse();
    }
});

test('administration page templates avoid wire navigate on internal links', function () {
    $templates = [
        'livewire/centers/manage-centers.blade.php',
        'livewire/centers/manage-center-form.blade.php',
        'livewire/centers/operating-calendar.blade.php',
        'livewire/users/manage-users.blade.php',
        'livewire/users/manage-user-form.blade.php',
        'livewire/settings/organization-settings.blade.php',
        'livewire/settings/whatsapp-settings.blade.php',
        'livewire/settings/security-settings.blade.php',
        'livewire/centers/center-selection.blade.php',
    ];

    foreach ($templates as $template) {
        $contents = file_get_contents(resource_path('views/'.$template));

        expect($contents)->not->toContain('wire:navigate', $template);
    }
});

test('administration navigation guard script is bundled', function () {
    $js = file_get_contents(resource_path('js/mf-navigation.js'));

    expect($js)->toContain('/centers')
        ->and($js)->toContain('window.location.assign');
});

test('administration form css targets flux field markup', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('.mf-card [data-flux-field]')
        ->and($css)->toContain('.mf-card [data-flux-input]')
        ->and($css)->toContain('.mf-table-panel__filters .mf-filter-field__control [data-flux-input]')
        ->and($css)->toContain('.mf-status-badge[data-mf-status-badge=\'success\']')
        ->and($css)->toContain('.mf-mobile-record-list')
        ->and($css)->toContain('.mf-manage-list-table')
        ->and($css)->toContain('.mf-admin-mobile-panel')
        ->and($css)->toContain('@media (max-width: 767px)')
        ->and($css)->toContain('.mf-mobile-record-card {');
});

test('manage centers and users render mobile card lists', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $center = createTestCenter($owner->organization, ['name' => 'Mobile Center']);

    Role::findOrCreate(RoleName::CenterManager, 'web');

    \App\Models\User::query()->create([
        'organization_id' => $owner->organization_id,
        'center_id' => $center->id,
        'name' => 'Mobile Manager',
        'username' => 'mobile-manager',
        'password' => bcrypt('password'),
        'is_active' => true,
        'must_change_password' => false,
    ])->assignRole(RoleName::CenterManager);

    $this->actingAs($owner)
        ->get(route('centers.index'))
        ->assertOk()
        ->assertSee('data-mf-mobile-record-list', false)
        ->assertSee('mf-mobile-record-card__mark', false)
        ->assertSee('mf-admin-mobile-panel', false)
        ->assertSee('mf-manage-list-table hidden min-w-0 md:block', false);

    $this->actingAs($owner)
        ->get(route('users.index'))
        ->assertOk()
        ->assertSee('data-mf-mobile-record-card', false)
        ->assertSee('mf-mobile-record-card__action', false)
        ->assertSee(__('user.manage.actions.reset_password_mobile'), false);
});

test('layouts force flux light appearance', function () {
    foreach (['components/layouts/app.blade.php', 'components/layouts/guest.blade.php', 'components/layouts/center-selection.blade.php'] as $layout) {
        $contents = file_get_contents(resource_path('views/'.$layout));

        expect($contents)->toContain('x-flux.forced-light-appearance')
            ->and($contents)->not->toContain('@fluxAppearance');
    }

    $appearance = file_get_contents(resource_path('views/components/flux/forced-light-appearance.blade.php'));

    expect($appearance)->toContain("applyAppearance('light')");
});

test('back link and secondary cancel button styles are defined', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('.mf-back-link {')
        ->and($css)->toContain('.mf-btn-secondary {')
        ->and($css)->toContain('.mf-form-actions');
});

test('admin forms use back link and secondary cancel buttons', function () {
    foreach ([
        'livewire/users/manage-user-form.blade.php',
        'livewire/centers/manage-center-form.blade.php',
        'livewire/centers/operating-calendar.blade.php',
    ] as $template) {
        $contents = file_get_contents(resource_path('views/'.$template));

        expect($contents)->toContain('x-ui.back-link')
            ->and($contents)->toContain('variant="secondary"');
    }
});

test('sidebar css keeps navigation scrollable with pinned profile on mobile', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('[data-flux-sidebar-nav]')
        ->and($css)->toContain('overflow-y: auto !important')
        ->and($css)->toContain('[data-flux-sidebar-spacer]')
        ->and($css)->toContain('display: none')
        ->and($css)->toContain('z-index: 100 !important')
        ->and($css)->toContain('inset-inline-start: 16rem !important')
        ->and($css)->toContain('data-flux-sidebar-collapsed-mobile');
});

test('desktop app shell pins sidebar while main content scrolls', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('@media (min-width: 1024px)')
        ->and($css)->toContain('body:has(.mf-app-shell)')
        ->and($css)->toContain('.mf-app-shell [data-flux-main].mf-app-main')
        ->and($css)->toContain('overflow-y: auto');
});

test('app shell sidebar omits flux sticky overflow conflict', function () {
    actingAsOwner();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('collapsible="mobile"', false);
    $response->assertDontSee(' sticky ', false);
    $response->assertSee('midnight-sidebar mf-sidebar', false);
});
