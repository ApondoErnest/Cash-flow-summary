<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner admin pages render full html content in main region', function (string $routeName, string $expectedText) {
    actingAsOwnerWithoutActiveCenter();

    $html = $this->get(route($routeName))->getContent();

    expect($html)->toContain('data-flux-main')
        ->and($html)->toContain($expectedText)
        ->and($html)->toContain('mf-page')
        ->and($html)->toContain('wire:snapshot')
        ->and(strlen($html))->toBeGreaterThan(8000);
})->with([
    ['centers.index', 'Manage Centers'],
    ['users.index', 'Manage Users'],
    ['settings.organization', 'Organization Settings'],
    ['settings.whatsapp', 'WhatsApp Settings'],
    ['security.index', 'Security Settings'],
]);
