<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    config([
        'csv_verification.temp_disk' => 'local',
        'csv_imports.permanent_disk' => 'local',
    ]);
    test()->seed(HeaderAliasSeeder::class);
});

test('ready catalogue fixtures commit successfully', function (string $fixture, ImportStatus $expectedStatus) {
    [$verification, $manager] = readyVerificationForCommit(loadCsvFixture($fixture));

    expect($verification->status)->toBe(VerificationStatus::Ready);

    $import = commitVerificationFor($manager, $verification);

    expect($import->status)->toBe($expectedStatus);
})->with([
    'sample_fr_valid.csv' => ['sample_fr_valid.csv', ImportStatus::Completed],
    'sample_fr_production_footer.csv' => ['sample_fr_production_footer.csv', ImportStatus::Completed],
    'sample_en_valid.csv' => ['sample_en_valid.csv', ImportStatus::Completed],
    'sample_real_patterns.csv' => ['sample_real_patterns.csv', ImportStatus::Completed],
    'zero_value_rows.csv' => ['zero_value_rows.csv', ImportStatus::Completed],
    'probable_duplicate_customer.csv' => ['probable_duplicate_customer.csv', ImportStatus::CompletedWithWarnings],
    'multi_day_period.csv' => ['multi_day_period.csv', ImportStatus::Completed],
    'duplicate_in_file.csv' => ['duplicate_in_file.csv', ImportStatus::CompletedWithDuplicates],
]);

test('failed catalogue fixtures cannot commit', function (string $fixture) {
    $verification = runVerificationPipelineForContents(loadCsvFixture($fixture));

    expect($verification->status)->toBe(VerificationStatus::Failed);

    $user = User::query()->findOrFail($verification->user_id);

    expect(fn () => commitVerificationFor($user, $verification))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'missing_footer.csv',
    'missing_header.csv',
    'mixed_headers.csv',
    'financial_mismatch.csv',
    'invalid_amount.csv',
    'invalid_date.csv',
]);
