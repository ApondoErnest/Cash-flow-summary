<?php

declare(strict_types=1);

use App\Modules\Centers\Services\OperatingCalendarService;
use App\Modules\WhatsApp\Enums\WhatsappEventType;
use App\Modules\WhatsApp\Jobs\SendWhatsAppNotificationJob;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Modules\WhatsApp\Support\WhatsAppCadenceResolver;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

afterEach(function () {
    Carbon::setTestNow();
});

test('cadence resolver includes daily summary on operating days only', function () {
    [$center] = whatsAppScheduledSummaryFixture();
    app(OperatingCalendarService::class)->ensureWeeklySchedule($center);
    $resolver = app(WhatsAppCadenceResolver::class);

    $wednesday = Carbon::parse('2026-07-08 18:00:00', config('app.timezone'));
    $sunday = Carbon::parse('2026-07-05 18:00:00', config('app.timezone'));

    expect($resolver->dueCadences($center, $wednesday))->toContain(WhatsappEventType::DailySummary)
        ->and($resolver->dueCadences($center, $sunday))->not->toContain(WhatsappEventType::DailySummary);
});

test('cadence resolver includes weekly summary on saturday', function () {
    [$center] = whatsAppScheduledSummaryFixture();
    $resolver = app(WhatsAppCadenceResolver::class);

    $saturday = Carbon::parse('2026-07-04 18:00:00', config('app.timezone'));

    expect($resolver->dueCadences($center, $saturday))->toContain(WhatsappEventType::WeeklySummary);
});

test('cadence resolver includes monthly and yearly summaries on last day of year', function () {
    [$center] = whatsAppScheduledSummaryFixture();
    $resolver = app(WhatsAppCadenceResolver::class);

    $newYearsEve = Carbon::parse('2026-12-31 18:00:00', config('app.timezone'));
    $cadences = $resolver->dueCadences($center, $newYearsEve);

    expect($cadences)->toContain(WhatsappEventType::MonthlySummary)
        ->and($cadences)->toContain(WhatsappEventType::YearlySummary);
});

test('dispatch command queues daily summary at center send time', function () {
    Queue::fake();
    Carbon::setTestNow(Carbon::parse('2026-07-08 18:00:00', config('app.timezone')));

    [$center] = whatsAppScheduledSummaryFixture(['whatsapp_summary_time' => '18:00']);

    $this->artisan('whatsapp:dispatch-scheduled-summaries')
        ->assertSuccessful();

    Queue::assertPushed(SendWhatsAppNotificationJob::class, 1);

    $message = WhatsappMessage::query()->first();
    expect($message)->not->toBeNull()
        ->and($message->center_id)->toBe($center->id)
        ->and($message->event_type)->toBe(WhatsappEventType::DailySummary->value);
});

test('dispatch command skips daily summary on non operating sunday', function () {
    Queue::fake();
    Carbon::setTestNow(Carbon::parse('2026-07-05 18:00:00', config('app.timezone')));

    whatsAppScheduledSummaryFixture(['whatsapp_summary_time' => '18:00']);

    $this->artisan('whatsapp:dispatch-scheduled-summaries')
        ->assertSuccessful();

    Queue::assertNotPushed(SendWhatsAppNotificationJob::class);
    expect(WhatsappMessage::query()->count())->toBe(0);
});

test('dispatch command queues multiple cadences on month end saturday', function () {
    Queue::fake();
    Carbon::setTestNow(Carbon::parse('2026-08-31 18:00:00', config('app.timezone')));

    whatsAppScheduledSummaryFixture(['whatsapp_summary_time' => '18:00']);

    $this->artisan('whatsapp:dispatch-scheduled-summaries')
        ->assertSuccessful();

    $eventTypes = WhatsappMessage::query()->pluck('event_type')->all();

    expect($eventTypes)->toContain(WhatsappEventType::DailySummary->value)
        ->and($eventTypes)->toContain(WhatsappEventType::MonthlySummary->value);

    Queue::assertPushed(SendWhatsAppNotificationJob::class, count($eventTypes));
});

test('dispatch command does not queue when send time does not match', function () {
    Queue::fake();
    Carbon::setTestNow(Carbon::parse('2026-07-08 17:59:00', config('app.timezone')));

    whatsAppScheduledSummaryFixture(['whatsapp_summary_time' => '18:00']);

    $this->artisan('whatsapp:dispatch-scheduled-summaries')
        ->assertSuccessful();

    Queue::assertNotPushed(SendWhatsAppNotificationJob::class);
});

test('dispatch command uses organization timezone for send-time matching', function () {
    Queue::fake();
    Carbon::setTestNow(Carbon::parse('2026-07-08 17:00:00', 'UTC'));

    [$center, $owner] = whatsAppScheduledSummaryFixture(['whatsapp_summary_time' => '18:00']);
    $owner->organization->forceFill(['timezone' => 'Africa/Douala'])->save();

    $this->artisan('whatsapp:dispatch-scheduled-summaries')
        ->assertSuccessful();

    Queue::assertPushed(SendWhatsAppNotificationJob::class, 1);
    expect(WhatsappMessage::query()->value('event_type'))->toBe(WhatsappEventType::DailySummary->value);
});
