<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\CsvImports\Models\Import;
use App\Modules\Settings\Enums\OrganizationSettingKey;
use App\Modules\Settings\Services\SettingsService;

function configureWhatsAppForOwner(User $owner): void
{
    $settings = app(SettingsService::class);
    $organizationId = (int) $owner->organization_id;

    $settings->set($organizationId, $owner, OrganizationSettingKey::WhatsappOwnerPhone, '+237612345678');
    $settings->set($organizationId, $owner, OrganizationSettingKey::WhatsappPhoneNumberId, '123456789012345');
    $settings->set($organizationId, $owner, OrganizationSettingKey::WhatsappAccessToken, 'EAAtest-access-token-value-123456');
}

function whatsAppImportFixture(): Import
{
    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()
        ->where('username', env('SEED_OWNER_USERNAME', 'owner'))
        ->firstOrFail();

    $center = createTestCenter($owner->organization, ['name' => 'WhatsApp Center']);
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);

    return commitVerificationFor($manager, $verification->fresh());
}

/**
 * @return array{0: \App\Modules\CsvVerification\Models\ImportVerification, 1: User, 2: User}
 */
function readyOwnerOrgVerificationForCommit(string $contents): array
{
    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()
        ->where('username', env('SEED_OWNER_USERNAME', 'owner'))
        ->firstOrFail();

    $center = createTestCenter($owner->organization, ['name' => 'WhatsApp Center']);
    $manager = actingAsManager($center);

    $verification = startVerificationFor($manager, $center, $contents);
    runProcessVerificationJob($verification->token);

    test()->actingAs($manager);

    return [$verification->fresh(), $manager, $owner];
}

/**
 * @return array{0: \App\Modules\CsvVerification\Models\ImportVerification, 1: User, 2: User}
 */
function readyHistoricalVerificationForCommit(bool $notifyOwner = false): array
{
    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()
        ->where('username', env('SEED_OWNER_USERNAME', 'owner'))
        ->firstOrFail();

    $center = createTestCenter($owner->organization, ['name' => 'WhatsApp Center']);
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
        \App\Modules\CsvVerification\Enums\ImportMode::Historical,
        $notifyOwner,
    );
    runProcessVerificationJob($verification->token);

    test()->actingAs($manager);

    return [$verification->fresh(), $manager, $owner];
}
