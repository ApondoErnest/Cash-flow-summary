<?php

declare(strict_types=1);

use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Livewire\DailyVersionList;
use App\Modules\DailyVersions\Livewire\RevisionApproval;
use App\Modules\DailyVersions\Models\DailyVersion;
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

test('daily versions page lists active version after import', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    commitVerificationFor($manager, $verification);

    $this->actingAs($manager)
        ->get(route('daily-versions.index'))
        ->assertOk()
        ->assertSee(__('daily_versions.list.title'), false)
        ->assertSee(__('daily_versions.status.active'), false)
        ->assertSee('11 925,00', false);
});

test('daily versions list filters by status', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    commitVerificationFor($manager, $verification);

    Livewire::actingAs($manager)
        ->test(DailyVersionList::class)
        ->set('statusFilter', DailyVersionStatus::Active->value)
        ->assertSee(__('daily_versions.status.active'), false)
        ->set('statusFilter', DailyVersionStatus::Proposed->value)
        ->assertSee(__('daily_versions.list.empty'), false);
});

test('daily versions list shows detail panel for selected version', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    commitVerificationFor($manager, $verification);

    $versionId = DailyVersion::query()->value('id');

    Livewire::actingAs($manager)
        ->test(DailyVersionList::class)
        ->call('selectVersion', $versionId)
        ->assertSet('selectedVersionId', $versionId)
        ->assertSee(__('daily_versions.list.detail_title'), false);
});

test('revision approval page lists pending revisions for owner', function () {
    [$import, $proposed, , $owner] = revisionApprovalFixture();

    $this->actingAs($owner)
        ->get(route('revisions.index'))
        ->assertOk()
        ->assertSee(__('daily_versions.revisions.title'), false)
        ->assertSee(__('daily_versions.revisions.review'), false)
        ->assertSee('11 925,00', false);

    expect($import->fresh()->status)->toBe(ImportStatus::AwaitingOwnerApproval);
    expect($proposed->fresh()->status)->toBe(DailyVersionStatus::Proposed);
});

test('owner can approve pending revision from approval page', function () {
    [, $proposed, $activeVersion, $owner] = revisionApprovalFixture();

    Livewire::actingAs($owner)
        ->test(RevisionApproval::class)
        ->call('selectRevision', $proposed->id)
        ->call('approve')
        ->assertHasNoErrors()
        ->assertSet('selectedRevisionId', null);

    expect($proposed->fresh()->status)->toBe(DailyVersionStatus::Active);
    expect($activeVersion->fresh()->status)->toBe(DailyVersionStatus::Superseded);
});

test('owner can reject pending revision with reason from approval page', function () {
    [, $proposed, , $owner] = revisionApprovalFixture();

    Livewire::actingAs($owner)
        ->test(RevisionApproval::class)
        ->call('selectRevision', $proposed->id)
        ->set('rejectReason', 'Totals do not match bank deposit')
        ->call('reject')
        ->assertHasNoErrors()
        ->assertSet('selectedRevisionId', null);

    expect($proposed->fresh()->status)->toBe(DailyVersionStatus::Rejected);
    expect($proposed->fresh()->rejected_reason)->toBe('Totals do not match bank deposit');
});

test('manager sees revision queue but cannot approve from ui', function () {
    [, $proposed, , , $manager] = revisionApprovalFixture();

    Livewire::actingAs($manager)
        ->test(RevisionApproval::class)
        ->assertSee(__('daily_versions.revisions.manager_notice'), false)
        ->assertSet('canApprove', false)
        ->call('selectRevision', $proposed->id)
        ->assertSee(__('daily_versions.revisions.comparison_title'), false)
        ->assertDontSee(__('daily_versions.revisions.approve'), false);

    expect($proposed->fresh()->status)->toBe(DailyVersionStatus::Proposed);
});

test('owner cannot select revision from another center', function () {
    [, $proposed, , $owner] = revisionApprovalFixture();

    $otherCenter = createTestCenter($owner->organization, ['code' => 'OTHER-'.uniqid()]);
    setOwnerActiveCenter($owner, $otherCenter);

    Livewire::actingAs($owner)
        ->test(RevisionApproval::class)
        ->call('selectRevision', $proposed->id)
        ->assertSet('selectedRevisionId', null);
});
