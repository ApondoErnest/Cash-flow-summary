<?php

declare(strict_types=1);

use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\Reports\Enums\ExportFormat;
use App\Modules\Reports\Enums\ExportRequestStatus;
use App\Modules\Reports\Enums\ReportType;
use App\Modules\Reports\Models\ExportRequest;
use App\Modules\Reports\Services\ExportCleanupService;
use App\Modules\Reports\Services\ExportService;
use App\Modules\Reports\Services\SummaryGenerationService;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    config([
        'csv_verification.temp_disk' => 'local',
        'csv_imports.permanent_disk' => 'local',
        'livewire.temporary_file_upload.disk' => 'local',
        'exports.disk' => 'local',
        'exports.ttl_hours' => 6,
    ]);
    test()->seed(HeaderAliasSeeder::class);
});

test('manager receives completed center report export file', function () {
    [, $manager, $export] = completedExportFixture();

    $response = $this->actingAs($manager)->get(signedDownloadUrl('exports.download', ['exportRequest' => $export->id]));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->headers->get('content-disposition'))
        ->toContain('center-report-dl-ctr-month.csv');

    expect(AuditLog::query()->where('event', 'export.downloaded')->where('resource_id', $export->id)->exists())->toBeTrue();
});

test('owner can download completed export for active center', function () {
    Carbon::setTestNow('2026-06-01 14:30:00');

    $owner = actingAsOwner();
    $center = createTestCenter($owner->organization, ['name' => 'Owner Download Center', 'code' => 'OWN-DL']);
    setOwnerActiveCenter($owner, $center);

    $manager = actingAsManager($center);
    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($manager, $verification->fresh());
    app(SummaryGenerationService::class)->regenerate($center->id, '2026-06-01');

    $export = ExportRequest::query()->create([
        'user_id' => $manager->id,
        'center_id' => $center->id,
        'report_type' => ReportType::CenterReport->value,
        'filters' => ['period' => 'month'],
        'format' => ExportFormat::Pdf,
        'status' => ExportRequestStatus::Pending,
    ]);

    app(ExportService::class)->generate($export->fresh());

    $this->actingAs($owner)
        ->get(signedDownloadUrl('exports.download', ['exportRequest' => $export->fresh()->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('manager cannot download another users export for the same center', function () {
    [$center, , $export] = completedExportFixture();
    $otherManager = actingAsManager($center);

    $this->actingAs($otherManager)
        ->get(signedDownloadUrl('exports.download', ['exportRequest' => $export->id]))
        ->assertForbidden();
});

test('user cannot download export from another center', function () {
    [, , $export] = completedExportFixture();
    $otherCenter = createTestCenter(attributes: ['name' => 'Other Center']);
    $otherManager = actingAsManager($otherCenter);

    $this->actingAs($otherManager)
        ->get(signedDownloadUrl('exports.download', ['exportRequest' => $export->id]))
        ->assertForbidden();
});

test('expired export download returns not found', function () {
    [, $manager, $export] = completedExportFixture();

    $export->update([
        'expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($manager)
        ->get(signedDownloadUrl('exports.download', ['exportRequest' => $export->id]))
        ->assertNotFound();
});

test('pending export download returns not found', function () {
    [$center, $manager] = completedExportFixture();

    $export = ExportRequest::query()->create([
        'user_id' => $manager->id,
        'center_id' => $center->id,
        'report_type' => ReportType::CenterReport->value,
        'filters' => ['period' => 'month'],
        'format' => ExportFormat::Csv,
        'status' => ExportRequestStatus::Pending,
    ]);

    $this->actingAs($manager)
        ->get(signedDownloadUrl('exports.download', ['exportRequest' => $export->id]))
        ->assertNotFound();
});

test('cashier cannot download report exports', function () {
    [$center, , $export] = completedExportFixture();
    $cashier = actingAsCashier($center);

    $this->actingAs($cashier)
        ->get(signedDownloadUrl('exports.download', ['exportRequest' => $export->id]))
        ->assertForbidden();
});

test('export cleanup service expires completed exports and deletes files', function () {
    [, , $export] = completedExportFixture();

    $path = (string) $export->storage_path;

    expect(Storage::disk('local')->exists($path))->toBeTrue();

    $export->update(['expires_at' => now()->subMinute()]);

    $result = app(ExportCleanupService::class)->run();

    expect($result->expired)->toBe(1)
        ->and($result->filesDeleted)->toBe(1)
        ->and($export->fresh()->status)->toBe(ExportRequestStatus::Expired)
        ->and($export->fresh()->storage_path)->toBeNull()
        ->and(Storage::disk('local')->exists($path))->toBeFalse();
});

test('exports cleanup command is scheduled hourly', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($scheduled) => str_contains($scheduled->command ?? '', 'exports:cleanup'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *');
});
