<?php

declare(strict_types=1);

use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Jobs\ProcessImportJob;
use App\Modules\CsvImports\Models\ImportRow;
use App\Modules\CsvImports\Services\ImportService;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    config([
        'csv_verification.temp_disk' => 'local',
        'csv_imports.permanent_disk' => 'local',
        'csv_imports.row_insert_chunk_size' => 2,
        'csv_imports.ledger_chunk_size' => 2,
        'livewire.temporary_file_upload.disk' => 'local',
    ]);
    test()->seed(HeaderAliasSeeder::class);
});

test('queued import commit dispatches process import job when sync is disabled', function () {
    Queue::fake();
    config(['csv_imports.process_synchronously' => false]);

    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = app(ImportService::class)->commitFromVerification($manager, $verification->token);

    expect($import->status)->toBe(ImportStatus::Processing);

    Queue::assertPushed(
        ProcessImportJob::class,
        fn (ProcessImportJob $job): bool => $job->importId === $import->id
            && $job->userId === $manager->id,
    );
});

test('process import job finalizes a processing import', function () {
    Queue::fake();
    config(['csv_imports.process_synchronously' => false]);

    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([
            completedFrenchDataRow(),
            implode(';', ['01/06/2026', '10:31', '02/06/2026', 'BETA SARL', 'VL', 'C', 'LT-111-AA', '10 000', '1 925', '11 925']),
            implode(';', ['01/06/2026', '10:32', '02/06/2026', 'GAMMA SARL', 'VL', 'C', 'LT-222-BB', '10 000', '1 925', '11 925']),
        ]),
    );

    $import = app(ImportService::class)->commitFromVerification($manager, $verification->token);

    expect($import->status)->toBe(ImportStatus::Processing)
        ->and(ImportRow::query()->where('import_id', $import->id)->count())->toBe(0);

    (new ProcessImportJob((int) $import->id, (int) $manager->id))->handle(
        app(ImportService::class),
        app(\App\Support\Center\JobCenterContextService::class),
    );

    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->new_master_count)->toBe(3)
        ->and(ImportRow::query()->where('import_id', $import->id)->count())->toBe(3);
});
