<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('composer manifest has no banned icon packages', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);
    $require = json_encode(array_merge(
        $composer['require'] ?? [],
        $composer['require-dev'] ?? [],
    ));

    foreach (['fontawesome', 'font-awesome', 'bootstrap-icons', 'blade-ui-kit/blade-icons'] as $banned) {
        expect($require)->not->toContain($banned);
    }

    expect($composer['require'])->toHaveKey('livewire/flux');
});

test('npm manifest has no standalone icon libraries', function () {
    $manifest = file_get_contents(base_path('package.json'));

    foreach (['@heroicons', 'heroicons', 'lucide', '@fortawesome', 'bootstrap-icons', '@iconify'] as $banned) {
        expect($manifest)->not->toContain($banned);
    }
});

test('owner dashboard renders flux heroicons from actions and navigation', function () {
    actingAsOwner();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('data-flux-icon', false);
    $response->assertSee(__('dashboard.actions.import_csv'), false);
});
