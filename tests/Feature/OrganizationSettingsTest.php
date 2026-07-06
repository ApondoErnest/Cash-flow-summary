<?php

declare(strict_types=1);

use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\Centers\Models\Organization;
use App\Modules\Settings\Livewire\OrganizationSettings;
use App\Modules\Settings\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('owner can save organization profile name code and contact details', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $organization = $owner->organization;

    Livewire::test(OrganizationSettings::class)
        ->set('name', 'NACHO Inspection Group')
        ->set('code', 'nacho-group')
        ->set('contactEmail', 'contact@nacho.example')
        ->set('contactPhone', '+237612345678')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee(__('settings.organization.saved'), false);

    $organization->refresh();

    expect($organization->name)->toBe('NACHO Inspection Group')
        ->and($organization->code)->toBe('NACHO-GROUP')
        ->and($organization->contact_details)->toBe([
            'email' => 'contact@nacho.example',
            'phone' => '+237612345678',
        ]);

    expect(AuditLog::query()->where('event', 'settings.updated')->exists())->toBeTrue();
});

test('owner can clear optional organization contact fields', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $organization = $owner->organization;

    $organization->update([
        'contact_details' => [
            'email' => 'old@example.com',
            'phone' => '+237600000000',
        ],
    ]);

    Livewire::test(OrganizationSettings::class)
        ->set('name', $organization->name)
        ->set('contactEmail', '')
        ->set('contactPhone', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($organization->fresh()->contact_details)->toBeNull();
});

test('organization profile save rejects invalid contact email', function () {
    actingAsOwnerWithoutActiveCenter();

    Livewire::test(OrganizationSettings::class)
        ->set('contactEmail', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['contactEmail' => 'email']);
});

test('organization profile save does not change read-only regional fields', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $organization = $owner->organization;

    $original = [
        'currency' => $organization->currency,
        'timezone' => $organization->timezone,
        'default_language' => $organization->default_language,
    ];

    Livewire::test(OrganizationSettings::class)
        ->set('name', 'Updated Name Only')
        ->set('code', 'UPDATED-CODE')
        ->call('save')
        ->assertHasNoErrors();

    $organization->refresh();

    expect($organization->name)->toBe('Updated Name Only')
        ->and($organization->code)->toBe('UPDATED-CODE')
        ->and($organization->currency)->toBe($original['currency'])
        ->and($organization->timezone)->toBe($original['timezone'])
        ->and($organization->default_language)->toBe($original['default_language']);
});

test('organization profile save rejects duplicate organization code', function () {
    Organization::query()->create([
        'name' => 'Other Organization',
        'code' => 'TAKEN-CODE',
    ]);

    actingAsOwnerWithoutActiveCenter();

    Livewire::test(OrganizationSettings::class)
        ->set('code', 'TAKEN-CODE')
        ->call('save')
        ->assertHasErrors(['code' => 'unique']);
});

test('settings service update organization profile persists changes', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $organization = Organization::query()->findOrFail($owner->organization_id);

    $updated = app(SettingsService::class)->updateOrganizationProfile(
        organization: $organization,
        user: $owner,
        payload: [
            'name' => 'Service Updated Org',
            'code' => 'SERVICE-ORG',
            'contact_email' => 'ops@example.com',
            'contact_phone' => null,
        ],
    );

    expect($updated->name)->toBe('Service Updated Org')
        ->and($updated->code)->toBe('SERVICE-ORG')
        ->and($updated->contact_details)->toBe(['email' => 'ops@example.com']);
});

test('staff cannot save organization settings', function () {
    actingAsManager();

    $this->get(route('settings.organization'))->assertForbidden();
});
