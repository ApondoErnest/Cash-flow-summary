<?php

declare(strict_types=1);

use App\Modules\Centers\Livewire\CenterSelection;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Livewire\CsvVerificationCard;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use App\Modules\DailyVersions\Livewire\RevisionApproval;
use App\Modules\Dashboards\Services\OwnerDashboardService;
use App\Modules\Dashboards\Enums\DashboardPeriod;
use App\Modules\Dashboards\Enums\DashboardTrendGranularity;
use App\Modules\Reports\Services\SummaryGenerationService;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

describe('Owner UAT staging journey (Step 106)', function () {
    test('owner admin access works without active center', function () {
        actingAsOwnerWithoutActiveCenter();

        $this->get(route('dashboard'))
            ->assertRedirect(route('center.select'));

        $this->get(route('centers.index'))
            ->assertOk()
            ->assertSee(__('center.manage.title'), false);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee(__('user.manage.title'), false);

        $this->get(route('settings.whatsapp'))
            ->assertOk()
            ->assertSee(__('settings.whatsapp.title'), false);
    });

    test('owner selects center switches context and scopes operational data', function () {
        $owner = actingAsOwnerWithoutActiveCenter();
        $centerA = createTestCenter($owner->organization, ['name' => 'UAT Center A']);
        $centerB = createTestCenter($owner->organization, ['name' => 'UAT Center B', 'code' => 'UAT-B']);

        Livewire::test(CenterSelection::class)
            ->set('centerId', $centerA->id)
            ->call('openCenter')
            ->assertRedirect(route('dashboard'));

        actingAsOwner($centerA);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('UAT Center A Cash-Flow Dashboard', false);

        $this->actingAs($owner)
            ->post(route('center.switch', $centerB))
            ->assertRedirect(route('dashboard'));

        actingAsOwner($centerB);

        expect(app(\App\Modules\Centers\Services\ActiveCenterContextService::class)->resolve($owner)?->centerId)
            ->toBe($centerB->id);
    });

    test('owner import page uses active center and supports verify import and reject', function () {
        $owner = actingAsOwner();
        $center = createTestCenter($owner->organization, ['name' => 'UAT Import Center']);
        setOwnerActiveCenter($owner, $center);

        $this->get(route('imports.create'))
            ->assertOk()
            ->assertSee('UAT Import Center', false)
            ->assertSee(__('csv_verification.card.center_label'), false);

        $fixtureContents = loadCsvFixture('sample_fr_production_footer.csv');

        $component = Livewire::test(CsvVerificationCard::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('uat-production-footer.csv', $fixtureContents))
            ->call('verify');

        runProcessVerificationJob($component->get('verificationToken'));

        $rejectToken = $component->call('refreshVerification')->get('verificationToken');

        Livewire::test(CsvVerificationCard::class)
            ->call('refreshVerification')
            ->set('verificationToken', $rejectToken)
            ->call('reject')
            ->assertSet('verificationToken', null);

        expect(ImportVerification::query()->where('token', $rejectToken)->value('status'))
            ->toBe(VerificationStatus::Rejected);
        expect(Import::query()->count())->toBe(0);

        $importComponent = Livewire::test(CsvVerificationCard::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('uat-import.csv', $fixtureContents))
            ->call('verify');

        runProcessVerificationJob($importComponent->get('verificationToken'));
        $importComponent->call('refreshVerification')->call('import');

        $import = Import::query()->latest('id')->firstOrFail();

        expect($import->center_id)->toBe($center->id)
            ->and($import->status)->toBe(ImportStatus::Completed);

        $importComponent->assertRedirect(route('imports.result', $import));
    });

    test('owner dashboard and reports reflect active center after import', function () {
        $owner = actingAsOwner();
        $activeCenter = createTestCenter($owner->organization, ['name' => 'Active UAT Center']);
        $otherCenter = createTestCenter($owner->organization, ['name' => 'Other UAT Center', 'code' => 'OTH-UAT']);
        setOwnerActiveCenter($owner, $activeCenter);

        $manager = actingAsManager($otherCenter);
        [$verification, $manager] = readyVerificationForCommit(loadCsvFixture('sample_fr_valid.csv'));
        commitVerificationFor($manager, $verification);
        app(SummaryGenerationService::class)->regenerate($otherCenter->id, '2026-06-01');

        actingAsOwner($activeCenter);

        $dashboard = app(OwnerDashboardService::class)->build(
            center: $activeCenter,
            period: DashboardPeriod::Month,
            trendGranularity: DashboardTrendGranularity::Daily,
        );

        expect($dashboard->centerName)->toBe('Active UAT Center')
            ->and($dashboard->totalTtc)->toBe('0,00');

        $this->get(route('reports.index'))
            ->assertOk()
            ->assertSee(__('reports.title'), false);
    });

    test('owner approves pending revision for active center', function () {
        [, $proposed, $activeVersion, $owner] = revisionApprovalFixture();

        Livewire::actingAs($owner)
            ->test(RevisionApproval::class)
            ->call('selectRevision', $proposed->id)
            ->call('approve')
            ->assertHasNoErrors();

        expect($proposed->fresh()->status)->toBe(DailyVersionStatus::Active)
            ->and($activeVersion->fresh()->status)->toBe(DailyVersionStatus::Superseded);
    });
});
