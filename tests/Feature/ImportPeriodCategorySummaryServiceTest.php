<?php

declare(strict_types=1);

use App\Modules\Dashboards\Services\ImportPeriodCategorySummaryService;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'csv_verification.temp_disk' => 'local',
        'csv_imports.permanent_disk' => 'local',
        'livewire.temporary_file_upload.disk' => 'local',
    ]);
    test()->seed(HeaderAliasSeeder::class);
});

test('import period category summary counts active masters in import period', function () {
    [$verification, $manager] = readyOwnerOrgVerificationForCommit(
        verificationReadyFrenchCsv([
            frenchDataRow(
                registrationDate: '01/06/2026',
                registrationTime: '10:30',
                completionDate: '02/06/2026',
                customerName: 'ACME SARL',
                categoryCode: 'A',
                inspectionTypeCode: 'VL',
                licencePlate: 'LT-123-AB',
                net: '10 000',
                vat: '1 925',
                ttc: '11 925',
            ),
        ], frenchFooterLine(1, 10_000, 1_925, 11_925)),
    );

    $import = commitVerificationFor($manager, $verification);

    $summary = app(ImportPeriodCategorySummaryService::class)->formatSummaryForImport($import);

    expect($summary)->toBe('A: 1, B: 0, B1: 0, C: 0, D: 0');
});
