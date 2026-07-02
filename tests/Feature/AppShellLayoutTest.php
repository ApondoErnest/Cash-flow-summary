<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('app shell renders flux sidebar header and main regions', function () {
    actingAsOwner();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('data-flux-sidebar', false);
    $response->assertSee('data-flux-header', false);
    $response->assertSee('data-flux-main', false);
    $response->assertSee('midnight-sidebar', false);
    $response->assertSee('mf-app-shell', false);
    $response->assertSee('Test Center', false);
    $response->assertSee('Manage Centers', false);
    $response->assertSee('Operations', false);
    $response->assertSee(__('auth.logout'), false);
});
