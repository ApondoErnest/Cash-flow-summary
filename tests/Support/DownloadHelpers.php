<?php

declare(strict_types=1);

use Illuminate\Support\Facades\URL;

/**
 * @param  array<string, mixed>  $parameters
 */
function signedDownloadUrl(string $routeName, array $parameters): string
{
    return URL::temporarySignedRoute(
        $routeName,
        now()->addMinutes((int) config('downloads.signed_url_ttl_minutes', 30)),
        $parameters,
    );
}

/**
 * @return array{0: \App\Modules\Centers\Models\Center, 1: \App\Models\User, 2: \App\Modules\Reports\Models\ExportRequest}
 */
function completedExportFixture(): array
{
    \Illuminate\Support\Carbon::setTestNow('2026-06-01 14:30:00');

    $center = createTestCenter(attributes: ['name' => 'Download Center', 'code' => 'DL-CTR']);
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);
    commitVerificationFor($manager, $verification->fresh());
    app(\App\Modules\Reports\Services\SummaryGenerationService::class)->regenerate($center->id, '2026-06-01');

    $export = \App\Modules\Reports\Models\ExportRequest::query()->create([
        'user_id' => $manager->id,
        'center_id' => $center->id,
        'report_type' => \App\Modules\Reports\Enums\ReportType::CenterReport->value,
        'filters' => ['period' => 'month'],
        'format' => \App\Modules\Reports\Enums\ExportFormat::Csv,
        'status' => \App\Modules\Reports\Enums\ExportRequestStatus::Pending,
    ]);

    app(\App\Modules\Reports\Services\ExportService::class)->generate($export->fresh());

    return [$center, $manager, $export->fresh()];
}
