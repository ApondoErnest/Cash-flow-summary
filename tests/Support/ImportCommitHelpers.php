<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\ImportDayComparison;
use App\Modules\CsvImports\Services\ImportService;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Models\ActiveDailySnapshot;
use App\Modules\DailyVersions\Models\DailyVersion;
use App\Modules\DailyVersions\Services\DailyDatasetService;
use App\Modules\DailyVersions\Support\DailyDataset;

function commitVerificationFor(User $user, ImportVerification $verification): Import
{
    return app(ImportService::class)->commitFromVerification($user, $verification->token);
}

/**
 * @return array{0: ImportVerification, 1: User}
 */
function readyVerificationForCommit(string $contents): array
{
    $verification = runVerificationPipelineForContents($contents);
    $user = User::query()->findOrFail($verification->user_id);

    test()->actingAs($user);

    return [$verification, $user];
}

/**
 * @return array{0: Import}
 */
function committedImportWithRows(string $contents): array
{
    $verification = runVerificationPipelineForContents($contents);
    $user = User::query()->findOrFail($verification->user_id);
    test()->actingAs($user);

    $import = commitVerificationFor($user, $verification);

    return [$import];
}

function activateDailyDatasetForImport(
    Import $import,
    string $businessDate,
    ?string $datasetHash = null,
): DailyVersion {
    ActiveDailySnapshot::query()
        ->withoutCenterScope()
        ->where('center_id', $import->center_id)
        ->whereDate('business_date', $businessDate)
        ->delete();

    DailyVersion::query()
        ->withoutCenterScope()
        ->where('center_id', $import->center_id)
        ->whereDate('business_date', $businessDate)
        ->delete();

    $dataset = app(DailyDatasetService::class)->buildFromImport($import, $businessDate);

    if ($datasetHash !== null) {
        $dataset = new DailyDataset(
            centerId: $dataset->centerId,
            businessDate: $dataset->businessDate,
            masterRecordIds: $dataset->masterRecordIds,
            datasetHash: $datasetHash,
            recordCount: $dataset->recordCount,
            totalHt: $dataset->totalHt,
            totalVat: $dataset->totalVat,
            totalTtc: $dataset->totalTtc,
        );
    }

    $version = DailyVersion::query()->create([
        'center_id' => $import->center_id,
        'business_date' => $businessDate,
        'import_id' => $import->id,
        'version_number' => 1,
        'dataset_hash' => $dataset->datasetHash,
        'record_count' => $dataset->recordCount,
        'total_ht' => $dataset->totalHt,
        'total_vat' => $dataset->totalVat,
        'total_ttc' => $dataset->totalTtc,
        'status' => DailyVersionStatus::Active,
    ]);

    ActiveDailySnapshot::query()->create([
        'center_id' => $import->center_id,
        'business_date' => $businessDate,
        'daily_version_id' => $version->id,
        'activated_at' => now(),
    ]);

    return $version;
}

/**
 * @return array{0: Import, 1: DailyVersion, 2: DailyVersion, 3: User, 4: User}
 */
function revisionApprovalFixture(): array
{
    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()
        ->where('username', env('SEED_OWNER_USERNAME', 'owner'))
        ->firstOrFail();

    $owner->forceFill(['must_change_password' => false])->save();

    $center = createTestCenter($owner->organization);
    $manager = actingAsManager($center);

    DailyVersion::query()->create([
        'center_id' => $center->id,
        'business_date' => '2026-06-01',
        'version_number' => 1,
        'dataset_hash' => hash('sha256', 'stale-active-dataset'),
        'record_count' => 1,
        'total_ht' => '10000.00',
        'total_vat' => '1925.00',
        'total_ttc' => '11925.00',
        'status' => DailyVersionStatus::Active,
    ]);

    $activeVersion = DailyVersion::query()->where('center_id', $center->id)->firstOrFail();

    ActiveDailySnapshot::query()->create([
        'center_id' => $center->id,
        'business_date' => '2026-06-01',
        'daily_version_id' => $activeVersion->id,
        'activated_at' => now(),
    ]);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);

    $import = commitVerificationFor($manager, $verification->fresh());

    $comparison = ImportDayComparison::query()
        ->withoutCenterScope()
        ->where('import_id', $import->id)
        ->firstOrFail();

    $proposed = DailyVersion::query()->findOrFail($comparison->proposed_version_id);
    setOwnerActiveCenter($owner, $center);
    test()->actingAs($owner);

    return [$import, $proposed, $activeVersion, $owner, $manager];
}
