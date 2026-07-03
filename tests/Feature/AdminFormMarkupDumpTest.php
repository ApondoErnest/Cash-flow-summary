<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dump center create form markup sample for flux element inspection', function () {
    actingAsOwnerWithoutActiveCenter();

    $html = $this->get(route('centers.create'))->getContent();

    preg_match('/<form[^>]*wire:submit="save"[^>]*>.*?<\/form>/s', $html, $matches);

    file_put_contents(
        storage_path('framework/testing/center-form-snippet.html'),
        $matches[0] ?? 'NO FORM MATCH'
    );

    expect($matches)->not->toBeEmpty();
});
