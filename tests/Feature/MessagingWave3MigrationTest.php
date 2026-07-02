<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\Organization;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\Notifications\Models\InternalNotification;
use App\Modules\WhatsApp\Enums\WhatsappMessageStatus;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('whatsapp messages migration creates table with data model columns', function () {
    expect(Schema::hasTable('whatsapp_messages'))->toBeTrue();
    expect(Schema::hasColumns('whatsapp_messages', [
        'id',
        'idempotency_key',
        'center_id',
        'import_id',
        'event_type',
        'recipient_phone',
        'template_name',
        'payload_summary',
        'status',
        'provider_message_id',
        'error_reason',
        'retry_count',
        'sent_at',
        'delivered_at',
        'read_at',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('internal notifications migration creates table with data model columns', function () {
    expect(Schema::hasTable('internal_notifications'))->toBeTrue();
    expect(Schema::hasColumns('internal_notifications', [
        'id',
        'user_id',
        'center_id',
        'type',
        'title',
        'body',
        'read_at',
        'related_type',
        'related_id',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('whatsapp message persists idempotent delivery lifecycle', function () {
    [$center, $import] = createMessagingMigrationFixtures();

    $message = WhatsappMessage::query()->create([
        'idempotency_key' => 'import-success:'.$import->id,
        'center_id' => $center->id,
        'import_id' => $import->id,
        'event_type' => 'import_success',
        'recipient_phone' => '+237600000000',
        'template_name' => 'import_success',
        'payload_summary' => [
            'center_name' => $center->name,
            'row_count' => 12,
            'total_ttc' => 119250,
        ],
        'status' => WhatsappMessageStatus::Sent,
        'provider_message_id' => 'wamid.test-message',
        'sent_at' => now(),
    ]);

    $message->refresh();

    expect($message->center->code)->toBe('MSG-CTR');
    expect($message->import->original_filename)->toBe('cashflow-june.csv');
    expect($message->status)->toBe(WhatsappMessageStatus::Sent);
    expect($message->payload_summary['row_count'])->toBe(12);
    expect($import->fresh()->whatsappMessages)->toHaveCount(1);
});

test('whatsapp messages enforce unique idempotency key', function () {
    [$center, $import] = createMessagingMigrationFixtures();
    $key = 'duplicate-key-'.uniqid();

    WhatsappMessage::query()->create([
        'idempotency_key' => $key,
        'center_id' => $center->id,
        'import_id' => $import->id,
        'event_type' => 'import_success',
        'recipient_phone' => '+237600000000',
        'payload_summary' => ['row_count' => 1],
        'status' => WhatsappMessageStatus::Queued,
    ]);

    expect(fn () => WhatsappMessage::query()->create([
        'idempotency_key' => $key,
        'center_id' => $center->id,
        'import_id' => $import->id,
        'event_type' => 'import_success',
        'recipient_phone' => '+237600000000',
        'payload_summary' => ['row_count' => 2],
        'status' => WhatsappMessageStatus::Queued,
    ]))->toThrow(QueryException::class);
});

test('internal notification persists inbox item with optional polymorphic link', function () {
    [$center, $import, $user] = createMessagingMigrationFixtures();

    $notification = InternalNotification::query()->create([
        'user_id' => $user->id,
        'center_id' => $center->id,
        'type' => 'revision_pending',
        'title' => 'Revision awaiting approval',
        'body' => 'Center '.$center->name.' submitted a revision for 2024-06-15.',
        'related_type' => Import::class,
        'related_id' => $import->id,
    ]);

    $notification->refresh();

    expect($notification->user->username)->toBe($user->username);
    expect($notification->center->code)->toBe('MSG-CTR');
    expect($notification->read_at)->toBeNull();
    expect($notification->related)->toBeInstanceOf(Import::class);
    expect($notification->related->id)->toBe($import->id);
});

test('internal notification can target user without center context', function () {
    [, , $user] = createMessagingMigrationFixtures();

    $notification = InternalNotification::query()->create([
        'user_id' => $user->id,
        'center_id' => null,
        'type' => 'system_notice',
        'title' => 'Maintenance scheduled',
        'body' => 'Reporting exports will be unavailable tonight.',
    ]);

    expect($notification->center_id)->toBeNull();
    expect($notification->related_type)->toBeNull();
});

test('messaging wave 3 migration runs after reports tables', function () {
    $migrationFiles = collect(scandir(database_path('migrations')))
        ->filter(fn (string $file) => str_ends_with($file, '.php'))
        ->values();

    $messagingIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100013_create_whatsapp_messages_and_internal_notifications'));
    $reportsIndex = $migrationFiles->search(fn (string $file) => str_contains($file, '100012_create_anomalies_summaries_and_export_requests'));

    expect($messagingIndex)->toBeGreaterThan($reportsIndex);
});

/**
 * @return array{0: Center, 1: Import, 2: User}
 */
function createMessagingMigrationFixtures(): array
{
    $organization = Organization::query()->create([
        'name' => 'Messaging Org',
        'code' => 'MSG-ORG-'.uniqid(),
    ]);

    $center = Center::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Messaging Center',
        'code' => 'MSG-CTR',
    ]);

    $user = User::query()->create([
        'organization_id' => $organization->id,
        'center_id' => $center->id,
        'name' => 'Messaging User',
        'username' => 'messaging.user.'.uniqid(),
        'password' => 'secret-password',
    ]);

    $verification = ImportVerification::query()->create([
        'token' => (string) Str::uuid(),
        'user_id' => $user->id,
        'center_id' => $center->id,
        'import_mode' => ImportMode::Operational,
        'original_filename' => 'cashflow-june.csv',
        'temp_storage_path' => 'temp/verifications/sample.csv',
        'file_size' => 4096,
        'file_hash' => hash('sha256', 'verify-'.uniqid()),
        'status' => VerificationStatus::Ready,
        'expires_at' => now()->addHours(2),
    ]);

    $import = Import::query()->create([
        'center_id' => $center->id,
        'import_verification_id' => $verification->id,
        'uploaded_by' => $user->id,
        'import_mode' => ImportMode::Operational,
        'source_language' => 'fr',
        'original_filename' => 'cashflow-june.csv',
        'storage_path' => 'imports/'.$center->id.'/cashflow-june.csv',
        'file_hash' => hash('sha256', 'import-'.uniqid()),
        'file_size' => 4096,
        'status' => ImportStatus::Completed,
    ]);

    return [$center, $import, $user];
}
