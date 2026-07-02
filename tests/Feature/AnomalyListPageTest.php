<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Reports\Livewire\AnomalyList;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    ]);
    test()->seed(HeaderAliasSeeder::class);
});

test('anomalies page lists open anomalies for manager', function () {
    [, , $manager] = anomalyListFixture();

    $this->actingAs($manager)
        ->get(route('anomalies.index'))
        ->assertOk()
        ->assertSee(__('anomalies.title'), false)
        ->assertSee(__('anomalies.types.probable_duplicate'), false)
        ->assertSee('ACME appears twice', false);
});

test('anomalies list filters by open resolution status', function () {
    [$anomaly, , $manager] = anomalyListFixture();

    Livewire::actingAs($manager)
        ->test(AnomalyList::class)
        ->set('resolutionFilter', 'open')
        ->assertSee(__('anomalies.resolution.open'), false)
        ->set('resolutionFilter', 'resolved')
        ->assertSee(__('anomalies.empty'), false);

    $anomaly->forceFill(['resolved_at' => now()])->save();

    Livewire::actingAs($manager)
        ->test(AnomalyList::class)
        ->set('resolutionFilter', 'resolved')
        ->assertSee(__('anomalies.resolution.resolved'), false);
});
