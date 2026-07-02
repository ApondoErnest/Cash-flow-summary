<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->seed(\Database\Seeders\HeaderAliasSeeder::class);
});

test('design system ui component blades exist', function () {
    foreach (['card', 'stat-card', 'button', 'table-panel', 'filter-bar', 'filter-field', 'status-badge'] as $component) {
        expect(file_exists(resource_path("views/components/ui/{$component}.blade.php")))->toBeTrue();
    }
});

test('owner dashboard renders reusable card button table and badge patterns', function () {
    $owner = actingAsOwner();
    $center = createTestCenter($owner->organization);
    setOwnerActiveCenter($owner, $center);
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($manager, $verification->fresh());

    actingAsOwner($center);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('data-mf-card', false);
    $response->assertSee('data-mf-stat-card', false);
    $response->assertSee('mf-btn-secondary', false);
    $response->assertSee('data-mf-table-panel', false);
    $response->assertSee('data-flux-table', false);
    $response->assertSee('data-mf-status-badge', false);
    $response->assertSee(__('dashboard.sections.recent_imports'), false);
    $response->assertSee(__('dashboard.actions.import_csv'), false);
});
