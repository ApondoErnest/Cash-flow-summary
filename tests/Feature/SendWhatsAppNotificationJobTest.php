<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\WhatsApp\Enums\WhatsappEventType;
use App\Modules\WhatsApp\Enums\WhatsappMessageStatus;
use App\Modules\WhatsApp\Exceptions\WhatsAppApiException;
use App\Modules\WhatsApp\Jobs\SendWhatsAppNotificationJob;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Modules\WhatsApp\Services\WhatsAppNotificationService;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

test('queue scheduled summary dispatches send job once', function () {
    Queue::fake();

    [$center] = whatsAppScheduledSummaryFixture();
    $moment = Carbon::parse('2026-07-08 18:00:00', config('app.timezone'));

    $message = app(WhatsAppNotificationService::class)->queueScheduledSummary(
        $center,
        WhatsappEventType::DailySummary,
        $moment,
    );

    expect($message)->not->toBeNull()
        ->and($message->status)->toBe(WhatsappMessageStatus::Queued);

    Queue::assertPushed(
        SendWhatsAppNotificationJob::class,
        fn (SendWhatsAppNotificationJob $job): bool => $job->whatsappMessageId === $message->id,
    );
    Queue::assertPushed(SendWhatsAppNotificationJob::class, 1);
});

test('queue scheduled summary does not create duplicate messages for same period', function () {
    Queue::fake();

    [$center] = whatsAppScheduledSummaryFixture();
    $moment = Carbon::parse('2026-07-08 18:00:00', config('app.timezone'));

    $service = app(WhatsAppNotificationService::class);
    $first = $service->queueScheduledSummary($center, WhatsappEventType::DailySummary, $moment);
    $second = $service->queueScheduledSummary($center->fresh(), WhatsappEventType::DailySummary, $moment);

    expect($first?->id)->toBe($second?->id)
        ->and(WhatsappMessage::query()->count())->toBe(1);

    Queue::assertPushed(SendWhatsAppNotificationJob::class, 2);
});

test('send whatsapp notification job sends queued message', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'messages' => [['id' => 'wamid.job-send']],
        ], 200),
    ]);

    [$center] = whatsAppScheduledSummaryFixture();
    $moment = Carbon::parse('2026-07-08 18:00:00', config('app.timezone'));

    $message = app(WhatsAppNotificationService::class)->prepareScheduledSummary(
        $center,
        WhatsappEventType::DailySummary,
        $moment,
    );
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

    [$center] = whatsAppScheduledSummaryFixture();
    $moment = Carbon::parse('2026-07-08 18:00:00', config('app.timezone'));

    $message = app(WhatsAppNotificationService::class)->prepareScheduledSummary(
        $center,
        WhatsappEventType::DailySummary,
        $moment,
    );
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

test('import commit does not queue whatsapp notification', function () {
    Queue::fake();

    [$verification, $manager, $owner] = readyOwnerOrgVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    configureWhatsAppForOwner($owner);

    commitVerificationFor($manager, $verification);

    Queue::assertNotPushed(SendWhatsAppNotificationJob::class);
});

test('historical import commit does not queue whatsapp notification even with owner opt in', function () {
    Queue::fake();

    [$verification, $manager, $owner] = readyHistoricalVerificationForCommit(notifyOwner: true);

    configureWhatsAppForOwner($owner);

    commitVerificationFor($manager, $verification);

    Queue::assertNotPushed(SendWhatsAppNotificationJob::class);
});
