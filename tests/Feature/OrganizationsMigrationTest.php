<?php

declare(strict_types=1);

use App\Modules\Centers\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('organizations migration creates table with data model columns', function () {
    expect(Schema::hasTable('organizations'))->toBeTrue();
    expect(Schema::hasColumns('organizations', [
        'id',
        'name',
        'code',
        'currency',
        'timezone',
        'default_language',
        'contact_details',
        'logo_path',
        'is_active',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('organization model persists with defaults', function () {
    $organization = Organization::query()->create([
        'name' => 'Demo Inspection Group',
        'code' => 'DEMO-ORG',
    ]);

    $organization->refresh();

    expect($organization->currency)->toBe('XAF');
    expect($organization->timezone)->toBe('Africa/Douala');
    expect($organization->default_language)->toBe('fr');
    expect($organization->is_active)->toBeTrue();
});

test('organization code must be unique', function () {
    Organization::query()->create([
        'name' => 'First Organization',
        'code' => 'UNIQUE-CODE',
    ]);

    expect(fn () => Organization::query()->create([
        'name' => 'Second Organization',
        'code' => 'UNIQUE-CODE',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
