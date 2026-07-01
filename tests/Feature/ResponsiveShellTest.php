<?php

declare(strict_types=1);

test('app shell includes mobile sidebar toggle and backdrop', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('mf-app-shell', false);
    $response->assertSee('collapsible="mobile"', false);
    $response->assertSee('data-flux-sidebar-toggle', false);
    $response->assertSee('data-flux-sidebar-backdrop', false);
    $response->assertSee('lg:hidden', false);
});

test('app shell uses compact mobile main padding classes', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('mf-app-main', false);
    $response->assertSee('!p-4 sm:!p-6 lg:!p-8', false);
});

test('welcome page uses responsive page and stat card grids', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('mf-page', false);
    $response->assertSee('grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4', false);
});

test('sidebar navigation closes on mobile via flux toggle dispatch', function () {
    $contents = file_get_contents(resource_path('views/components/navigation/sidebar.blade.php'));

    expect($contents)->toContain('flux-sidebar-toggle');
    expect($contents)->toContain('max-width: 1023px');
});
