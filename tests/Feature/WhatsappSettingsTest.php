<?php

declare(strict_types=1);

use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\Settings\Enums\OrganizationSettingKey;
use App\Modules\Settings\Livewire\WhatsappSettings;
use App\Modules\Settings\Models\OrganizationSetting;
use App\Modules\Settings\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('owner can save whatsapp settings', function () {
    $owner = actingAsOwnerWithoutActiveCenter();

    Livewire::test(WhatsappSettings::class)
        ->set('ownerPhone', '+237612345678')
        ->set('phoneNumberId', '123456789012345')
        ->set('accessToken', 'EAAtest-access-token-value-123456')
        ->set('webhookVerifyToken', 'verify-token-secret')
        ->call('save')
        ->assertHasNoErrors();

    $organizationId = (int) $owner->organization_id;

    expect(app(SettingsService::class)->get($organizationId, OrganizationSettingKey::WhatsappOwnerPhone))
        ->toBe('+237612345678')
        ->and(app(SettingsService::class)->get($organizationId, OrganizationSettingKey::WhatsappPhoneNumberId))
        ->toBe('123456789012345');

    $storedToken = OrganizationSetting::query()
        ->where('organization_id', $organizationId)
        ->where('key', OrganizationSettingKey::WhatsappAccessToken->value)
        ->value('value');

    expect($storedToken)->not->toBe('EAAtest-access-token-value-123456')
        ->and(Crypt::decryptString((string) $storedToken))->toBe('EAAtest-access-token-value-123456');

    expect(AuditLog::query()->where('event', 'settings.updated')->exists())->toBeTrue();
});

test('owner can update whatsapp settings without re-entering secrets', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $organizationId = (int) $owner->organization_id;

    Livewire::test(WhatsappSettings::class)
        ->set('ownerPhone', '+237612345678')
        ->set('phoneNumberId', '123456789012345')
        ->set('accessToken', 'EAAtest-access-token-value-123456')
        ->set('webhookVerifyToken', 'verify-token-secret')
        ->call('save');

    Livewire::test(WhatsappSettings::class)
        ->set('ownerPhone', '+237698765432')
        ->set('phoneNumberId', '987654321098765')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsService::class)->get($organizationId, OrganizationSettingKey::WhatsappOwnerPhone))
        ->toBe('+237698765432')
        ->and(Crypt::decryptString((string) OrganizationSetting::query()
            ->where('organization_id', $organizationId)
            ->where('key', OrganizationSettingKey::WhatsappAccessToken->value)
            ->value('value')))->toBe('EAAtest-access-token-value-123456');
});

test('whatsapp settings page shows configured notice after save', function () {
    actingAsOwnerWithoutActiveCenter();

    Livewire::test(WhatsappSettings::class)
        ->set('ownerPhone', '+237612345678')
        ->set('phoneNumberId', '123456789012345')
        ->set('accessToken', 'EAAtest-access-token-value-123456')
        ->set('webhookVerifyToken', 'verify-token-secret')
        ->call('save')
        ->assertSee(__('settings.whatsapp.configured_notice'), false);
});

test('whatsapp settings validates owner phone format', function () {
    actingAsOwnerWithoutActiveCenter();

    Livewire::test(WhatsappSettings::class)
        ->set('ownerPhone', '612345678')
        ->set('phoneNumberId', '123456789012345')
        ->set('accessToken', 'EAAtest-access-token-value-123456')
        ->set('webhookVerifyToken', 'verify-token-secret')
        ->call('save')
        ->assertHasErrors(['ownerPhone']);
});

test('staff cannot access whatsapp settings page', function () {
    actingAsManager();

    $this->get(route('settings.whatsapp'))->assertForbidden();
});
