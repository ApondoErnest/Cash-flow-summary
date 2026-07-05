<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\WhatsApp\Enums\WhatsappMessageStatus;
use App\Modules\WhatsApp\Exceptions\WhatsAppApiException;
use App\Modules\WhatsApp\Jobs\SendWhatsAppNotificationJob;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Modules\WhatsApp\Services\WhatsAppNotificationService;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'csv_verification.temp_disk' => 'local',
        'csv_imports.permanent_disk' => 'local',
        'livewire.temporary_file_upload.disk' => 'local',
    ]);
    test()->seed(HeaderAliasSeeder::class);
});

test('queue import notification dispatches send job once', function () {
    Queue::fake();

    $import = whatsAppImportFixture();
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppForOwner($owner);

    $message = app(WhatsAppNotificationService::class)->queueImportNotification($import);

    expect($message)->not->toBeNull()
        ->and($message->status)->toBe(WhatsappMessageStatus::Queued);

    Queue::assertPushed(
        SendWhatsAppNotificationJob::class,
        fn (SendWhatsAppNotificationJob $job): bool => $job->whatsappMessageId === $message->id,
    );
    Queue::assertPushed(SendWhatsAppNotificationJob::class, 1);
});

test('queue import notification does not dispatch duplicate jobs for same import', function () {
    Queue::fake();

    $import = whatsAppImportFixture();
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppForOwner($owner);

    $service = app(WhatsAppNotificationService::class);
    $first = $service->queueImportNotification($import);
    $second = $service->queueImportNotification($import->fresh());

    expect($first?->id)->toBe($second?->id);

    Queue::assertPushed(SendWhatsAppNotificationJob::class, 2);
});

test('send whatsapp notification job sends queued message', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'messages' => [['id' => 'wamid.job-send']],
        ], 200),
    ]);

    $import = whatsAppImportFixture();
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppForOwner($owner);

    $message = app(WhatsAppNotificationService::class)->prepareImportNotification($import);
    expect($message)->not->toBeNull();

    (new SendWhatsAppNotificationJob((int) $message->id))->handle(
        app(WhatsAppNotificationService::class),
        app(\App\Support\Center\JobCenterContextService::class),
    );

    $message->refresh();

    expect($message->status)->toBe(WhatsappMessageStatus::Sent)
        ->and($message->provider_message_id)->toBe('wamid.job-send');
});

test('send whatsapp notification job marks message failed after final attempt', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Rate limit hit'],
        ], 429),
    ]);

    $import = whatsAppImportFixture();
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    configureWhatsAppForOwner($owner);

    $message = app(WhatsAppNotificationService::class)->prepareImportNotification($import);
    expect($message)->not->toBeNull();

    $job = new SendWhatsAppNotificationJob((int) $message->id);

    try {
        $job->handle(
            app(WhatsAppNotificationService::class),
            app(\App\Support\Center\JobCenterContextService::class),
        );
    } catch (WhatsAppApiException) {
        // expected
    }

    $message->refresh();
    expect($message->status)->toBe(WhatsappMessageStatus::Queued)
        ->and($message->retry_count)->toBe(1);

    $job->failed(new WhatsAppApiException('Rate limit hit', 429));

    $message->refresh();
    expect($message->status)->toBe(WhatsappMessageStatus::Failed)
        ->and($message->error_reason)->toBe('Rate limit hit');
});

test('import commit queues whatsapp notification when settings are configured', function () {
    Queue::fake();

    [$verification, $manager, $owner] = readyOwnerOrgVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    configureWhatsAppForOwner($owner);

    $import = commitVerificationFor($manager, $verification);

    Queue::assertPushed(SendWhatsAppNotificationJob::class, function (SendWhatsAppNotificationJob $job) use ($import): bool {
        $message = WhatsappMessage::query()->find($job->whatsappMessageId);

        return $message !== null
            && $message->import_id === $import->id
            && $message->status === WhatsappMessageStatus::Queued;
    });
});

test('import commit does not queue whatsapp notification when settings are missing', function () {
    Queue::fake();

    [$verification, $manager] = readyOwnerOrgVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    commitVerificationFor($manager, $verification);

    Queue::assertNotPushed(SendWhatsAppNotificationJob::class);
});

test('historical import commit does not queue whatsapp notification without owner opt in', function () {
    Queue::fake();

    [$verification, $manager, $owner] = readyHistoricalVerificationForCommit(notifyOwner: false);

    configureWhatsAppForOwner($owner);

    commitVerificationFor($manager, $verification);

    Queue::assertNotPushed(SendWhatsAppNotificationJob::class);
});

test('historical import commit queues whatsapp notification when owner opt in is checked', function () {
    Queue::fake();

    [$verification, $manager, $owner] = readyHistoricalVerificationForCommit(notifyOwner: true);

    configureWhatsAppForOwner($owner);

    $import = commitVerificationFor($manager, $verification);

    Queue::assertPushed(SendWhatsAppNotificationJob::class, function (SendWhatsAppNotificationJob $job) use ($import): bool {
        $message = WhatsappMessage::query()->find($job->whatsappMessageId);

        return $message !== null
            && $message->import_id === $import->id
            && $message->event_type === 'historical_import';
    });
});
