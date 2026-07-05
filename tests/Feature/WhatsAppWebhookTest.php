<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Settings\Enums\OrganizationSettingKey;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\WhatsApp\Enums\WhatsappMessageStatus;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Modules\WhatsApp\Models\WhatsappWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function configureWhatsAppWebhooksForOwner(User $owner, string $verifyToken = 'verify-token-secret'): void
{
    configureWhatsAppForOwner($owner);

    $settings = app(SettingsService::class);
    $settings->set(
        (int) $owner->organization_id,
        $owner,
        OrganizationSettingKey::WhatsappWebhookVerifyToken,
        $verifyToken,
    );
}

/**
 * @param  list<array<string, mixed>>  $statuses
 * @return array<string, mixed>
 */
function whatsAppWebhookPayload(array $statuses, string $phoneNumberId = '123456789012345'): array
{
    return [
        'object' => 'whatsapp_business_account',
        'entry' => [
            [
                'id' => 'WABA_ID',
                'changes' => [
                    [
                        'field' => 'messages',
                        'value' => [
                            'messaging_product' => 'whatsapp',
                            'metadata' => [
                                'display_phone_number' => '15550001111',
                                'phone_number_id' => $phoneNumberId,
                            ],
                            'statuses' => $statuses,
                        ],
                    ],
                ],
            ],
        ],
    ];
}

function whatsAppWebhookSignature(string $rawBody, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', $rawBody, $secret);
}

function createSentWhatsappMessage(string $providerMessageId = 'wamid.webhook-test'): WhatsappMessage
{
    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    $center = createTestCenter($owner->organization, ['name' => 'WhatsApp Center']);

    return WhatsappMessage::query()->create([
        'idempotency_key' => 'webhook_test:'.$providerMessageId,
        'center_id' => $center->id,
        'import_id' => null,
        'event_type' => 'import_success',
        'recipient_phone' => '+237612345678',
        'template_name' => 'import_success',
        'payload_summary' => ['center_name' => 'WhatsApp Center'],
        'status' => WhatsappMessageStatus::Sent,
        'provider_message_id' => $providerMessageId,
        'retry_count' => 0,
        'sent_at' => now(),
    ]);
}

test('whatsapp webhook verify returns 404 when webhooks are not configured', function () {
    $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=token&hub_challenge=12345')
        ->assertNotFound();
});

test('whatsapp webhook verify returns challenge when verify token matches', function () {
    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppWebhooksForOwner($owner);

    $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=verify-token-secret&hub_challenge=12345')
        ->assertOk()
        ->assertSee('12345');
});

test('whatsapp webhook verify returns forbidden when verify token is wrong', function () {
    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppWebhooksForOwner($owner);

    $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong-token&hub_challenge=12345')
        ->assertForbidden();
});

test('whatsapp webhook receive returns 404 when webhooks are not configured', function () {
    config(['whatsapp.app_secret' => 'meta-app-secret']);

    $payload = whatsAppWebhookPayload([
        [
            'id' => 'wamid.ignored',
            'status' => 'delivered',
            'timestamp' => '1710000000',
            'recipient_id' => '237612345678',
        ],
    ]);

    $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        'X-Hub-Signature-256' => whatsAppWebhookSignature($rawBody, 'meta-app-secret'),
    ])->assertNotFound();
});

test('whatsapp webhook receive rejects invalid signature', function () {
    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppWebhooksForOwner($owner);
    config(['whatsapp.app_secret' => 'meta-app-secret']);

    $payload = whatsAppWebhookPayload([
        [
            'id' => 'wamid.invalid-signature',
            'status' => 'delivered',
            'timestamp' => '1710000000',
            'recipient_id' => '237612345678',
        ],
    ]);

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        'X-Hub-Signature-256' => 'sha256=invalid',
    ])->assertForbidden();
});

test('whatsapp webhook receive updates message to delivered', function () {
    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppWebhooksForOwner($owner);
    config(['whatsapp.app_secret' => 'meta-app-secret']);

    $message = createSentWhatsappMessage('wamid.delivered');

    $payload = whatsAppWebhookPayload([
        [
            'id' => 'wamid.delivered',
            'status' => 'delivered',
            'timestamp' => '1710000000',
            'recipient_id' => '237612345678',
        ],
    ]);

    $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        'X-Hub-Signature-256' => whatsAppWebhookSignature($rawBody, 'meta-app-secret'),
    ])->assertOk();

    $message->refresh();

    expect($message->status)->toBe(WhatsappMessageStatus::Delivered)
        ->and($message->delivered_at)->not->toBeNull()
        ->and(WhatsappWebhookEvent::query()->count())->toBe(1);
});

test('whatsapp webhook receive updates message to read', function () {
    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppWebhooksForOwner($owner);
    config(['whatsapp.app_secret' => 'meta-app-secret']);

    $message = createSentWhatsappMessage('wamid.read');

    $payload = whatsAppWebhookPayload([
        [
            'id' => 'wamid.read',
            'status' => 'read',
            'timestamp' => '1710000100',
            'recipient_id' => '237612345678',
        ],
    ]);

    $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        'X-Hub-Signature-256' => whatsAppWebhookSignature($rawBody, 'meta-app-secret'),
    ])->assertOk();

    $message->refresh();

    expect($message->status)->toBe(WhatsappMessageStatus::Read)
        ->and($message->read_at)->not->toBeNull();
});

test('whatsapp webhook receive updates message to failed with error reason', function () {
    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppWebhooksForOwner($owner);
    config(['whatsapp.app_secret' => 'meta-app-secret']);

    $message = createSentWhatsappMessage('wamid.failed');

    $payload = whatsAppWebhookPayload([
        [
            'id' => 'wamid.failed',
            'status' => 'failed',
            'timestamp' => '1710000200',
            'recipient_id' => '237612345678',
            'errors' => [
                ['code' => 131026, 'title' => 'Delivery failed', 'message' => 'Message undeliverable'],
            ],
        ],
    ]);

    $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        'X-Hub-Signature-256' => whatsAppWebhookSignature($rawBody, 'meta-app-secret'),
    ])->assertOk();

    $message->refresh();

    expect($message->status)->toBe(WhatsappMessageStatus::Failed)
        ->and($message->error_reason)->toBe('Message undeliverable');
});

test('whatsapp webhook receive is idempotent for duplicate provider events', function () {
    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppWebhooksForOwner($owner);
    config(['whatsapp.app_secret' => 'meta-app-secret']);

    $message = createSentWhatsappMessage('wamid.duplicate-event');

    $payload = whatsAppWebhookPayload([
        [
            'id' => 'wamid.duplicate-event',
            'status' => 'delivered',
            'timestamp' => '1710000300',
            'recipient_id' => '237612345678',
        ],
    ]);

    $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
    $headers = ['X-Hub-Signature-256' => whatsAppWebhookSignature($rawBody, 'meta-app-secret')];

    $this->postJson('/api/webhooks/whatsapp', $payload, $headers)->assertOk();
    $this->postJson('/api/webhooks/whatsapp', $payload, $headers)->assertOk();

    expect(WhatsappWebhookEvent::query()->count())->toBe(1)
        ->and($message->fresh()->status)->toBe(WhatsappMessageStatus::Delivered);
});

test('whatsapp webhook receive does not downgrade read status to delivered', function () {
    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppWebhooksForOwner($owner);
    config(['whatsapp.app_secret' => 'meta-app-secret']);

    $message = createSentWhatsappMessage('wamid.no-downgrade');
    $message->forceFill([
        'status' => WhatsappMessageStatus::Read,
        'read_at' => now(),
        'delivered_at' => now(),
    ])->save();

    $payload = whatsAppWebhookPayload([
        [
            'id' => 'wamid.no-downgrade',
            'status' => 'delivered',
            'timestamp' => '1710000400',
            'recipient_id' => '237612345678',
        ],
    ]);

    $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->postJson('/api/webhooks/whatsapp', $payload, [
        'X-Hub-Signature-256' => whatsAppWebhookSignature($rawBody, 'meta-app-secret'),
    ])->assertOk();

    expect($message->fresh()->status)->toBe(WhatsappMessageStatus::Read);
});
