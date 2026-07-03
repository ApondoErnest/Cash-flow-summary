<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('manage user create form html includes flux input controls and labels', function () {
    actingAsOwnerWithoutActiveCenter();
    createTestCenter(actingAsOwnerWithoutActiveCenter()->organization);

    $html = $this->get(route('users.create'))->getContent();

    expect($html)->toContain(__('user.manage.fields.name'))
        ->and($html)->toContain(__('user.manage.fields.username'))
        ->and($html)->toContain(__('user.manage.fields.role'))
        ->and($html)->toContain('wire:model="name"')
        ->and($html)->toContain('wire:model="username"')
        ->and($html)->toContain('data-flux-control');
});

test('manage center create form html includes flux input controls and labels', function () {
    actingAsOwnerWithoutActiveCenter();

    $html = $this->get(route('centers.create'))->getContent();

    expect($html)->toContain(__('center.manage.fields.name'))
        ->and($html)->toContain(__('center.manage.fields.code'))
        ->and($html)->toContain('wire:model="name"')
        ->and($html)->toContain('wire:model="code"')
        ->and($html)->toContain('data-flux-control');
});
