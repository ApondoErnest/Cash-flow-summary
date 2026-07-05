<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Settings\Enums\OrganizationSettingKey;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\WhatsApp\Exceptions\WhatsAppApiException;
use App\Modules\WhatsApp\Services\WhatsAppCloudApiClient;
use App\Modules\WhatsApp\Support\WhatsAppCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function whatsAppApiCredentials(): WhatsAppCredentials
{
    return new WhatsAppCredentials(
        ownerPhone: '+237612345678',
        phoneNumberId: '123456789012345',
        accessToken: 'EAAtest-access-token-value-123456',
    );
}

test('whatsapp cloud api client sends template message to meta graph api', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'messaging_product' => 'whatsapp',
            'messages' => [
                ['id' => 'wamid.test-message-id'],
            ],
        ], 200),
    ]);

    $result = app(WhatsAppCloudApiClient::class)->sendTemplateMessage(
        credentials: whatsAppApiCredentials(),
        recipientPhone: '+237612345678',
        templateName: 'import_success',
        languageCode: 'en',
        bodyParameters: ['Center A', '01/06/2026', '12', '1 000', '200', '1 200', 'Manager'],
    );

    expect($result->providerMessageId)->toBe('wamid.test-message-id');

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $request->url() === 'https://graph.facebook.com/v21.0/123456789012345/messages'
            && $request->hasHeader('Authorization', 'Bearer EAAtest-access-token-value-123456')
            && ($body['to'] ?? null) === '237612345678'
            && ($body['template']['name'] ?? null) === 'import_success'
            && ($body['template']['language']['code'] ?? null) === 'en'
            && count($body['template']['components'][0]['parameters'] ?? []) === 7;
    });
});

test('whatsapp cloud api client throws when meta returns an error', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'error' => [
                'message' => 'Invalid OAuth access token.',
                'type' => 'OAuthException',
                'code' => 190,
            ],
        ], 401),
    ]);

    app(WhatsAppCloudApiClient::class)->sendTemplateMessage(
        credentials: whatsAppApiCredentials(),
        recipientPhone: '+237612345678',
        templateName: 'import_success',
        languageCode: 'en',
        bodyParameters: [],
    );
})->throws(WhatsAppApiException::class, 'Invalid OAuth access token.');

test('settings service exposes whatsapp credentials when outbound settings are complete', function () {
    $owner = actingAsOwnerWithoutActiveCenter();

    app(SettingsService::class)->set(
        (int) $owner->organization_id,
        $owner,
        OrganizationSettingKey::WhatsappOwnerPhone,
        '+237612345678',
    );
    app(SettingsService::class)->set(
        (int) $owner->organization_id,
        $owner,
        OrganizationSettingKey::WhatsappPhoneNumberId,
        '123456789012345',
    );
    app(SettingsService::class)->set(
        (int) $owner->organization_id,
        $owner,
        OrganizationSettingKey::WhatsappAccessToken,
        'EAAtest-access-token-value-123456',
    );

    $credentials = app(SettingsService::class)->whatsAppCredentials((int) $owner->organization_id);

    expect($credentials)->not->toBeNull()
        ->and($credentials->ownerPhone)->toBe('+237612345678')
        ->and($credentials->phoneNumberId)->toBe('123456789012345')
        ->and($credentials->accessToken)->toBe('EAAtest-access-token-value-123456');
});
