<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

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

test('sidebar navigation uses wire navigate and closes drawer after navigation', function () {
    $sidebar = file_get_contents(resource_path('views/components/navigation/sidebar.blade.php'));
    $js = file_get_contents(resource_path('js/mf-sidebar.js'));

    expect($sidebar)->toContain('wire:navigate');
    expect($sidebar)->not->toContain('flux-sidebar-toggle');
    expect($js)->toContain('livewire:navigated');
    expect($js)->toContain('flux-sidebar-toggle');
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

test('app shell sidebar omits flux sticky overflow conflict', function () {
    actingAsOwner();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('collapsible="mobile"', false);
    $response->assertDontSee(' sticky ', false);
    $response->assertSee('midnight-sidebar mf-sidebar', false);
});
