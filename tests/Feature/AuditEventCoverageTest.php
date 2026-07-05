<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\AuditLogging\Services\AuditLogService;
use App\Modules\Authentication\Services\LoginService;
use App\Modules\Centers\Services\CenterService;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Services\VerificationService;
use App\Modules\Users\Services\UserService;
use App\Modules\WhatsApp\Enums\WhatsappMessageStatus;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Support\Auth\RoleName;
use Database\Seeders\HeaderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use App\Modules\WhatsApp\Livewire\WhatsappHistoryPage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'csv_verification.temp_disk' => 'local',
        'csv_imports.permanent_disk' => 'local',
    ]);
    test()->seed(HeaderAliasSeeder::class);
});

test('successful login records audit event with center context for staff', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);
    auth()->logout();

    app(LoginService::class)->authenticate(
        username: $manager->username,
        password: env('SEED_OWNER_PASSWORD', 'password'),
        remember: false,
        ipAddress: '127.0.0.1',
    );

    expect(AuditLog::query()->where('event', 'login')->where('user_id', $manager->id)->exists())->toBeTrue();
});

test('failed login records audit event without authenticated user', function () {
    createTestCenter();

    try {
        app(LoginService::class)->authenticate(
            username: 'unknown-user',
            password: 'wrong-password',
            remember: false,
            ipAddress: '127.0.0.1',
        );
    } catch (ValidationException) {
        // expected
    }

    $log = AuditLog::query()->where('event', 'login.failed')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBeNull()
        ->and($log->new_values['username'] ?? null)->toBe('unknown-user');
});

test('center create and update record audit events', function () {
    $owner = actingAsOwnerWithoutActiveCenter();

    $center = app(CenterService::class)->create($owner, [
        'name' => 'Audit Center',
        'code' => 'AUD-01',
        'is_active' => true,
    ]);

    app(CenterService::class)->update($center, $owner, [
        'name' => 'Audit Center Updated',
        'code' => 'AUD-01',
        'is_active' => true,
    ]);

    expect(AuditLog::query()->where('event', 'center.created')->where('resource_id', $center->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'center.updated')->where('resource_id', $center->id)->exists())->toBeTrue();
});

test('user lifecycle records audit events for create reset and reassignment', function () {
    $owner = actingAsOwnerWithoutActiveCenter();
    $centerA = createTestCenter($owner->organization, ['name' => 'Center A']);
    $centerB = createTestCenter($owner->organization, ['name' => 'Center B']);

    $result = app(UserService::class)->create($owner, [
        'name' => 'Audit Staff',
        'username' => 'audit-staff',
        'role' => RoleName::Cashier,
        'center_id' => $centerA->id,
    ]);

    $user = $result['user'];

    app(UserService::class)->update($owner, $user, [
        'name' => 'Audit Staff',
        'username' => 'audit-staff',
        'role' => RoleName::Cashier,
        'center_id' => $centerB->id,
        'is_active' => true,
    ]);

    app(UserService::class)->resetPassword($owner, $user->fresh(), app(\App\Modules\Authentication\Services\PasswordService::class));

    expect(AuditLog::query()->where('event', 'user.created')->where('resource_id', $user->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'user.reassigned')->where('resource_id', $user->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'user.password_reset')->where('resource_id', $user->id)->exists())->toBeTrue();
});

test('verification failure audit excludes csv body content', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    $verification = startVerificationFor($manager, $center, loadCsvFixture('missing_footer.csv'));
    runProcessVerificationJob($verification->token);

    $verification->refresh();

    expect($verification->status)->toBe(VerificationStatus::Failed);

    $log = AuditLog::query()->where('event', 'verification.failed')->first();

    expect($log)->not->toBeNull()
        ->and($log->reason)->not->toBeNull()
        ->and(json_encode($log->new_values))->not->toContain('amount')
        ->and($log->new_values['filename'] ?? null)->toBe('cashflow-june.csv');
});

test('verification reject audit excludes csv body content', function () {
    $center = createTestCenter();
    $manager = actingAsManager($center);

    $verification = startVerificationFor($manager, $center, verificationReadyFrenchCsv([completedFrenchDataRow()]));
    runProcessVerificationJob($verification->token);

    app(VerificationService::class)->reject($manager, $verification->fresh());

    $log = AuditLog::query()->where('event', 'verification.rejected')->first();

    expect($log)->not->toBeNull()
        ->and(json_encode($log->new_values))->not->toContain('HT')
        ->and($log->new_values['filename'] ?? null)->toBe('cashflow-june.csv');
});

test('import commit records import created audit event', function () {
    [$verification, $manager] = readyVerificationForCommit(
        verificationReadyFrenchCsv([completedFrenchDataRow()]),
    );

    $import = commitVerificationFor($manager, $verification);

    expect(AuditLog::query()
        ->where('event', 'import.created')
        ->where('resource_id', $import->id)
        ->where('center_id', $import->center_id)
        ->exists())->toBeTrue();
});

test('whatsapp resend records audit event', function () {
    Queue::fake();

    test()->seed(\Database\Seeders\DatabaseSeeder::class);

    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    $owner->forceFill(['must_change_password' => false])->save();

    $center = createTestCenter($owner->organization, ['name' => 'WhatsApp Center']);
    setOwnerActiveCenter($owner, $center);
    test()->actingAs($owner);

    $message = WhatsappMessage::query()->create([
        'idempotency_key' => 'audit:resend:test',
        'center_id' => $center->id,
        'event_type' => 'import_success',
        'recipient_phone' => '+237612345678',
        'template_name' => 'import_success',
        'payload_summary' => ['center_name' => 'WhatsApp Center'],
        'status' => WhatsappMessageStatus::Failed,
        'retry_count' => 3,
        'error_reason' => 'Delivery failed',
    ]);

    Livewire::test(WhatsappHistoryPage::class)
        ->call('selectMessage', $message->id)
        ->call('resendMessage')
        ->assertSet('selectedMessageId', $message->id);

    expect(AuditLog::query()->where('event', 'whatsapp.resent')->where('resource_id', $message->id)->exists())->toBeTrue()
        ->and($message->fresh()->status)->toBe(WhatsappMessageStatus::Queued);
});

test('audit log service labels plan events', function () {
    $service = app(AuditLogService::class);

    expect($service->eventLabel('login'))->toBe(__('audit.events.login'))
        ->and($service->eventLabel('verification.failed'))->toBe(__('audit.events.verification_failed'))
        ->and($service->eventLabel('whatsapp.resent'))->toBe(__('audit.events.whatsapp_resent'));
});
