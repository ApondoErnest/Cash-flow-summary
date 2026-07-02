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

test('manager can resolve anomaly from detail panel', function () {
    [$anomaly, , $manager] = anomalyListFixture();

    Livewire::actingAs($manager)
        ->test(AnomalyList::class)
        ->call('selectAnomaly', $anomaly->id)
        ->call('resolve')
        ->assertHasNoErrors()
        ->assertSet('selectedAnomalyId', null);

    expect($anomaly->fresh()->resolved_at)->not->toBeNull();
});

test('owner cannot select anomaly from another center', function () {
    [$anomaly] = anomalyListFixture();

    $owner = User::query()
        ->where('username', env('SEED_OWNER_USERNAME', 'owner'))
        ->firstOrFail();

    $otherCenter = createTestCenter($owner->organization, ['code' => 'OTHER-'.uniqid()]);
    setOwnerActiveCenter($owner, $otherCenter);

    Livewire::actingAs($owner)
        ->test(AnomalyList::class)
        ->call('selectAnomaly', $anomaly->id)
        ->assertSet('selectedAnomalyId', null);
});
