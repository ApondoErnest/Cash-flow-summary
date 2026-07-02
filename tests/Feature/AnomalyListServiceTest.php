<?php

declare(strict_types=1);

use App\Modules\Reports\Services\AnomalyListService;
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
        'livewire.temporary_file_upload.disk' => 'local',
    ]);
    test()->seed(HeaderAliasSeeder::class);
});

test('anomaly list service maps detail metadata rows', function () {
    [$anomaly, , $manager] = anomalyListFixture();

    test()->actingAs($manager);

    $detail = app(AnomalyListService::class)->toDetail($anomaly->fresh(), true);

    expect($detail->typeLabel)->toBe(__('anomalies.types.probable_duplicate'))
        ->and($detail->metadataRows)->not->toBeEmpty()
        ->and($detail->canResolve)->toBeTrue();
});
