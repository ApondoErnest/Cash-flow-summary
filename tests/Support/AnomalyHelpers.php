<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Reports\Enums\AnomalyType;
use App\Modules\Reports\Models\Anomaly;
use App\Modules\WhatsApp\Enums\WhatsappMessageStatus;
use App\Modules\WhatsApp\Models\WhatsappMessage;

/**
 * @return array{0: Anomaly, 1: \App\Modules\CsvImports\Models\Import, 2: User}
 */
function anomalyListFixture(): array
{
    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()
        ->where('username', env('SEED_OWNER_USERNAME', 'owner'))
        ->firstOrFail();

    $owner->forceFill(['must_change_password' => false])->save();

    $center = createTestCenter($owner->organization);
    $manager = actingAsManager($center);

    $verification = startVerificationFor(
        $manager,
        $center,
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );
    runProcessVerificationJob($verification->token);

    $import = commitVerificationFor($manager, $verification->fresh());

    $anomaly = Anomaly::query()->create([
        'center_id' => $import->center_id,
        'import_id' => $import->id,
        'type' => AnomalyType::ProbableDuplicate->value,
        'description' => 'Customer ACME appears twice with similar totals.',
        'metadata' => ['customer' => 'ACME', 'similarity' => 0.94],
    ]);

    return [$anomaly, $import, $manager];
}

/**
 * @return array{0: WhatsappMessage, 1: \App\Modules\CsvImports\Models\Import, 2: User, 3: User}
 */
function whatsappHistoryFixture(): array
{
    [, $import, $manager] = anomalyListFixture();

    $owner = User::query()
        ->where('username', env('SEED_OWNER_USERNAME', 'owner'))
        ->firstOrFail();

    setOwnerActiveCenter($owner, $import->center);
    test()->actingAs($owner);

    $message = WhatsappMessage::query()->create([
        'idempotency_key' => 'history-test-'.uniqid(),
        'center_id' => $import->center_id,
        'import_id' => $import->id,
        'event_type' => 'import_success',
        'recipient_phone' => '+237600000000',
        'template_name' => 'import_success',
        'payload_summary' => [
            'center_name' => $import->center->name,
            'row_count' => 1,
        ],
        'status' => WhatsappMessageStatus::Sent,
        'sent_at' => now(),
    ]);

    return [$message, $import, $owner, $manager];
}
