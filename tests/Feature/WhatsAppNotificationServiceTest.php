<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\WhatsApp\Enums\WhatsappEventType;
use App\Modules\WhatsApp\Enums\WhatsappMessageStatus;
use App\Modules\WhatsApp\Exceptions\WhatsAppApiException;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Modules\WhatsApp\Services\WhatsAppNotificationService;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'csv_verification.temp_disk' => 'local',
        'csv_imports.permanent_disk' => 'local',
        'livewire.temporary_file_upload.disk' => 'local',
    ]);
    test()->seed(HeaderAliasSeeder::class);
});

test('whatsapp notification service sends import success message', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'messages' => [['id' => 'wamid.import-success']],
        ], 200),
    ]);

    $import = whatsAppImportFixture();
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppForOwner($owner);

    $message = app(WhatsAppNotificationService::class)->notifyForImport($import);

    expect($message)->not->toBeNull()
        ->and($message->status)->toBe(WhatsappMessageStatus::Sent)
        ->and($message->event_type)->toBe(WhatsappEventType::ImportSuccess->value)
        ->and($message->provider_message_id)->toBe('wamid.import-success')
        ->and($message->recipient_phone)->toBe('+237612345678')
        ->and($message->payload_summary['center_name'] ?? null)->toBe('WhatsApp Center')
        ->and($message->sent_at)->not->toBeNull();

    Http::assertSentCount(1);
});

test('whatsapp notification service returns null when outbound settings are incomplete', function () {
    $import = whatsAppImportFixture();

    $message = app(WhatsAppNotificationService::class)->notifyForImport($import);

    expect($message)->toBeNull()
        ->and(WhatsappMessage::query()->count())->toBe(0);

    Http::assertNothingSent();
});

test('whatsapp notification service respects idempotency key for duplicate requests', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'messages' => [['id' => 'wamid.once-only']],
        ], 200),
    ]);

    $import = whatsAppImportFixture();
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppForOwner($owner);

    $service = app(WhatsAppNotificationService::class);
    $first = $service->notifyForImport($import);
    $second = $service->notifyForImport($import->fresh());

    expect($first?->id)->toBe($second?->id)
        ->and(WhatsappMessage::query()->count())->toBe(1);

    Http::assertSentCount(1);
});

test('whatsapp notification service resolves import with duplicates event type', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'messages' => [['id' => 'wamid.duplicates']],
        ], 200),
    ]);

    $import = whatsAppImportFixture();
    $import->forceFill([
        'duplicate_within_file_count' => 2,
        'new_master_count' => 3,
    ])->save();

    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppForOwner($owner);

    $message = app(WhatsAppNotificationService::class)->notifyForImport($import->fresh());

    expect($message?->event_type)->toBe(WhatsappEventType::ImportWithDuplicates->value)
        ->and($message?->template_name)->toBe('import_with_duplicates');
});

test('whatsapp notification service marks message failed when meta api rejects request', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Template name does not exist in the translation'],
        ], 400),
    ]);

    $import = whatsAppImportFixture();
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppForOwner($owner);

    try {
        app(WhatsAppNotificationService::class)->notifyForImport($import);
    } catch (WhatsAppApiException) {
        // expected
    }

    $message = WhatsappMessage::query()->first();

    expect($message)->not->toBeNull()
        ->and($message->status)->toBe(WhatsappMessageStatus::Queued)
        ->and($message->retry_count)->toBe(1)
        ->and($message->error_reason)->toContain('Template name does not exist');
});

test('whatsapp notification service builds deterministic idempotency keys', function () {
    $service = app(WhatsAppNotificationService::class);

    expect($service->idempotencyKey(WhatsappEventType::ImportSuccess, 42))
        ->toBe('import_success:import:42')
        ->and($service->idempotencyKey(WhatsappEventType::RevisionApproved, 42, 7))
        ->toBe('revision_approved:import:42:revision:7');
});

test('whatsapp notification service skips historical imports without owner opt in', function () {
    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppForOwner($owner);

    [$verification, $manager] = readyHistoricalVerificationForCommit(notifyOwner: false);
    $import = commitVerificationFor($manager, $verification);

    expect(app(WhatsAppNotificationService::class)->shouldQueueImportNotification($import))->toBeFalse()
        ->and(app(WhatsAppNotificationService::class)->queueImportNotification($import))->toBeNull()
        ->and(WhatsappMessage::query()->count())->toBe(0);
});

test('whatsapp notification service resolves historical import event type when opted in', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'messages' => [['id' => 'wamid.historical']],
        ], 200),
    ]);

    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppForOwner($owner);

    [$verification, $manager] = readyHistoricalVerificationForCommit(notifyOwner: true);
    $import = commitVerificationFor($manager, $verification);

    $message = app(WhatsAppNotificationService::class)->notifyForImport($import);

    expect($message)->not->toBeNull()
        ->and($message->event_type)->toBe(WhatsappEventType::HistoricalImport->value)
        ->and($message->template_name)->toBe('historical_import');
});
