<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('design system ui component blades exist', function () {
    foreach (['card', 'stat-card', 'button', 'table-panel', 'status-badge'] as $component) {
        expect(file_exists(resource_path("views/components/ui/{$component}.blade.php")))->toBeTrue();
    }
});

test('welcome page renders reusable card button table and badge patterns', function () {
    actingAsOwner();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('data-mf-card', false);
    $response->assertSee('data-mf-stat-card', false);
    $response->assertSee('data-mf-button="approval"', false);
    $response->assertSee('mf-btn-secondary', false);
    $response->assertSee('data-mf-table-panel', false);
    $response->assertSee('data-flux-table', false);
    $response->assertSee('data-mf-status-badge="success"', false);
    $response->assertSee('Recent imports', false);
    $response->assertSee('Approve revision', false);
});
