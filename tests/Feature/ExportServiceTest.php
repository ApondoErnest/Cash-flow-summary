<?php

declare(strict_types=1);

use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\Dashboards\Enums\DashboardPeriod;
use App\Modules\Reports\Enums\ExportFormat;
use App\Modules\Reports\Enums\ExportRequestStatus;
use App\Modules\Reports\Enums\ReportType;
use App\Modules\Reports\Jobs\GenerateExportJob;
use App\Modules\Reports\Livewire\CenterReport;
use App\Modules\Reports\Models\ExportRequest;
use App\Modules\Reports\Services\ExportService;
use App\Modules\Reports\Services\SummaryGenerationService;
use App\Modules\Reports\Support\CenterReportExportBuilder;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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

function exportServiceFixture(): array
{
    Carbon::setTestNow('2026-06-01 14:30:00');

    $center = createTestCenter(attributes: ['name' => 'Export Center']);
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($manager, $verification->fresh());
    app(SummaryGenerationService::class)->regenerate($center->id, '2026-06-01');

    return [$center, $manager];
}

test('export service queues center report export and records audit event', function () {
    [$center, $manager] = exportServiceFixture();

    $export = app(ExportService::class)->requestCenterReportExport(
        user: $manager,
        center: $center,
        format: ExportFormat::Csv,
        period: DashboardPeriod::Month,
    );

    expect($export->status)->toBe(ExportRequestStatus::Pending)
        ->and($export->report_type)->toBe(ReportType::CenterReport->value)
        ->and($export->center_id)->toBe($center->id)
        ->and($export->user_id)->toBe($manager->id)
        ->and($export->filters)->toBe(['period' => 'month']);

    Queue::assertPushed(GenerateExportJob::class, fn (GenerateExportJob $job): bool => $job->exportRequestId === $export->id);

    expect(AuditLog::query()
        ->where('event', 'export.requested')
        ->where('resource_id', $export->id)
        ->exists())->toBeTrue();
});

test('export service stores custom period filters on export request', function () {
    [$center, $manager] = exportServiceFixture();

    $export = app(ExportService::class)->requestCenterReportExport(
        user: $manager,
        center: $center,
        format: ExportFormat::Pdf,
        period: DashboardPeriod::Custom,
        customFrom: Carbon::parse('2026-06-01')->startOfDay(),
        customTo: Carbon::parse('2026-06-01')->endOfDay(),
    );

    expect($export->filters)->toBe([
        'period' => 'custom',
        'from' => '2026-06-01',
        'to' => '2026-06-01',
    ]);
});

test('generate export job writes csv file and marks export completed', function () {
    [$center, $manager] = exportServiceFixture();

    $export = ExportRequest::query()->create([
        'user_id' => $manager->id,
        'center_id' => $center->id,
        'report_type' => ReportType::CenterReport->value,
        'filters' => ['period' => 'month'],
        'format' => ExportFormat::Csv,
        'status' => ExportRequestStatus::Pending,
    ]);

    app(ExportService::class)->generate($export->fresh());

    $export->refresh();

    expect($export->status)->toBe(ExportRequestStatus::Completed)
        ->and($export->storage_path)->not->toBeNull()
        ->and($export->completed_at)->not->toBeNull()
        ->and($export->expires_at)->not->toBeNull()
        ->and(Storage::disk('local')->exists((string) $export->storage_path))->toBeTrue();

    $contents = Storage::disk('local')->get((string) $export->storage_path);

    expect($contents)->toContain('Export Center')
        ->and($contents)->toContain('11 925,00')
        ->and($contents)->toContain('01/06/2026');
});

test('generate export job writes xlsx and pdf files', function (ExportFormat $format) {
    [$center, $manager] = exportServiceFixture();

    $export = ExportRequest::query()->create([
        'user_id' => $manager->id,
        'center_id' => $center->id,
        'report_type' => ReportType::CenterReport->value,
        'filters' => ['period' => 'month'],
        'format' => $format,
        'status' => ExportRequestStatus::Pending,
    ]);

    app(ExportService::class)->generate($export->fresh());

    $export->refresh();

    expect($export->status)->toBe(ExportRequestStatus::Completed)
        ->and(str_ends_with((string) $export->storage_path, '.'.$format->value))->toBeTrue()
        ->and(Storage::disk('local')->exists((string) $export->storage_path))->toBeTrue();

    $contents = Storage::disk('local')->get((string) $export->storage_path);

    if ($format === ExportFormat::Xlsx) {
        expect($contents)->toStartWith('PK');
    }

    if ($format === ExportFormat::Pdf) {
        expect($contents)->toStartWith('%PDF');
    }
})->with([
    ExportFormat::Xlsx,
    ExportFormat::Pdf,
]);

test('generate export job marks export failed when generation throws', function () {
    [$center, $manager] = exportServiceFixture();

    $export = ExportRequest::query()->create([
        'user_id' => $manager->id,
        'center_id' => $center->id,
        'report_type' => ReportType::CenterReport->value,
        'filters' => ['period' => 'month'],
        'format' => ExportFormat::Csv,
        'status' => ExportRequestStatus::Pending,
    ]);

    $builder = Mockery::mock(CenterReportExportBuilder::class);
    $builder->shouldReceive('build')->andThrow(new RuntimeException('Export generation failed.'));
    $builder->shouldReceive('extension')->andReturn('csv');
    app()->instance(CenterReportExportBuilder::class, $builder);

    expect(fn () => app(ExportService::class)->generate($export->fresh()))
        ->toThrow(RuntimeException::class);

    expect($export->fresh()->status)->toBe(ExportRequestStatus::Failed);
});

test('manager can queue csv export from reports page', function () {
    [$center, $manager] = exportServiceFixture();

    Livewire::actingAs($manager)
        ->test(CenterReport::class)
        ->call('requestExport', ExportFormat::Csv->value);

    expect(ExportRequest::query()->count())->toBe(1)
        ->and(ExportRequest::query()->first()->format)->toBe(ExportFormat::Csv);

    Queue::assertPushed(GenerateExportJob::class);
});

test('owner can queue excel export from reports page', function () {
    Carbon::setTestNow('2026-06-01 14:30:00');

    $owner = actingAsOwner();
    $center = createTestCenter($owner->organization, ['name' => 'Owner Export Center']);
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

    Livewire::actingAs($owner)
        ->test(CenterReport::class)
        ->call('requestExport', ExportFormat::Xlsx->value);

    expect(ExportRequest::query()->count())->toBe(1)
        ->and(ExportRequest::query()->value('user_id'))->toBe($owner->id);
});
