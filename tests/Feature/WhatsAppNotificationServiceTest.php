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
use Illuminate\Support\Carbon;
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

test('whatsapp notification service sends daily summary message', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'messages' => [['id' => 'wamid.daily-summary']],
        ], 200),
    ]);

    [$center] = whatsAppScheduledSummaryFixture();
    $moment = Carbon::parse('2026-07-08 18:00:00', config('app.timezone'));

    $message = app(WhatsAppNotificationService::class)->notifyScheduledSummary(
        $center,
        WhatsappEventType::DailySummary,
        $moment,
    );

    expect($message)->not->toBeNull()
        ->and($message->status)->toBe(WhatsappMessageStatus::Sent)
        ->and($message->event_type)->toBe(WhatsappEventType::DailySummary->value)
        ->and($message->template_name)->toBe('import_activity_summary')
        ->and($message->provider_message_id)->toBe('wamid.daily-summary')
        ->and($message->recipient_phone)->toBe('+237612345678')
        ->and($message->payload_summary['center_name'] ?? null)->toBe('WhatsApp Center')
        ->and($message->payload_summary['category_summary'] ?? null)->toBeString()
        ->and($message->sent_at)->not->toBeNull();

    Http::assertSent(function ($request): bool {
        $body = $request->data();
        $params = $body['template']['components'][0]['parameters'] ?? [];

        return ($body['template']['name'] ?? null) === 'import_activity_summary'
            && ($body['template']['language']['code'] ?? null) === 'en'
            && count($params) === 7
            && ($params[0]['parameter_name'] ?? null) === 'center_name'
            && ($params[0]['text'] ?? null) === 'WhatsApp Center';
    });
});

test('whatsapp notification service returns null when outbound settings are incomplete', function () {
    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()
        ->where('username', env('SEED_OWNER_USERNAME', 'owner'))
        ->firstOrFail();

    $center = createTestCenter($owner->organization, ['name' => 'WhatsApp Center']);
    $moment = Carbon::parse('2026-07-08 18:00:00', config('app.timezone'));

    $message = app(WhatsAppNotificationService::class)->notifyScheduledSummary(
        $center,
        WhatsappEventType::DailySummary,
        $moment,
    );

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

    [$center] = whatsAppScheduledSummaryFixture();
    $moment = Carbon::parse('2026-07-08 18:00:00', config('app.timezone'));

    $service = app(WhatsAppNotificationService::class);
    $first = $service->notifyScheduledSummary($center, WhatsappEventType::DailySummary, $moment);
    $second = $service->notifyScheduledSummary($center->fresh(), WhatsappEventType::DailySummary, $moment);

    expect($first?->id)->toBe($second?->id)
        ->and(WhatsappMessage::query()->count())->toBe(1);

    Http::assertSentCount(1);
});

test('whatsapp notification service marks message failed when meta api rejects request', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Template name does not exist in the translation'],
        ], 400),
    ]);

    [$center] = whatsAppScheduledSummaryFixture();
    $moment = Carbon::parse('2026-07-08 18:00:00', config('app.timezone'));

    try {
        app(WhatsAppNotificationService::class)->notifyScheduledSummary(
            $center,
            WhatsappEventType::DailySummary,
            $moment,
        );
    } catch (WhatsAppApiException) {
        // expected
    }

    $message = WhatsappMessage::query()->first();

    expect($message)->not->toBeNull()
        ->and($message->status)->toBe(WhatsappMessageStatus::Queued)
        ->and($message->retry_count)->toBe(1)
        ->and($message->error_reason)->toContain('Template name does not exist');
});

test('whatsapp notification service builds deterministic scheduled summary idempotency keys', function () {
    $service = app(WhatsAppNotificationService::class);

    expect($service->scheduledSummaryIdempotencyKey(
        WhatsappEventType::DailySummary,
        42,
        '2026-07-08',
    ))->toBe('daily_summary:center:42:2026-07-08')
        ->and($service->scheduledSummaryIdempotencyKey(
            WhatsappEventType::WeeklySummary,
            7,
            '2026-W28',
        ))->toBe('weekly_summary:center:7:2026-W28');
});

test('whatsapp notification service resolves weekly summary event type', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'messages' => [['id' => 'wamid.weekly']],
        ], 200),
    ]);

    [$center] = whatsAppScheduledSummaryFixture();
    $moment = Carbon::parse('2026-07-05 18:00:00', config('app.timezone'));

    $message = app(WhatsAppNotificationService::class)->notifyScheduledSummary(
        $center,
        WhatsappEventType::WeeklySummary,
        $moment,
    );

    expect($message?->event_type)->toBe(WhatsappEventType::WeeklySummary->value)
        ->and($message?->template_name)->toBe('import_activity_summary')
        ->and($message?->payload_summary['period_key'] ?? null)->toBe('2026-W27');
});

test('whatsapp notification service skips non scheduled event types when queuing', function () {
    [$center] = whatsAppScheduledSummaryFixture();
    $moment = Carbon::parse('2026-07-08 18:00:00', config('app.timezone'));

    $message = app(WhatsAppNotificationService::class)->queueScheduledSummary(
        $center,
        WhatsappEventType::ImportSuccess,
        $moment,
    );

    expect($message)->toBeNull()
        ->and(WhatsappMessage::query()->count())->toBe(0);
});
