<?php

declare(strict_types=1);

use App\Modules\WhatsApp\Enums\WhatsappMessageStatus;
use App\Modules\WhatsApp\Livewire\WhatsappHistoryPage;
use App\Modules\WhatsApp\Services\WhatsappHistoryService;
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

test('whatsapp history page lists messages for owner', function () {
    [, , $owner] = whatsappHistoryFixture();

    $this->actingAs($owner)
        ->get(route('whatsapp-history.index'))
        ->assertOk()
        ->assertSee(__('whatsapp.history.title'), false)
        ->assertSee(__('whatsapp.event_type.import_success'), false)
        ->assertSee('+237600000000', false);
});

test('whatsapp history filters by failed status', function () {
    [$message, , $owner] = whatsappHistoryFixture();

    Livewire::actingAs($owner)
        ->test(WhatsappHistoryPage::class)
        ->set('statusFilter', WhatsappMessageStatus::Failed->value)
        ->assertSee(__('whatsapp.history.empty'), false);

    $message->forceFill(['status' => WhatsappMessageStatus::Failed, 'error_reason' => 'Provider timeout'])->save();

    Livewire::actingAs($owner)
        ->test(WhatsappHistoryPage::class)
        ->set('statusFilter', WhatsappMessageStatus::Failed->value)
        ->assertSee(__('whatsapp.status.failed'), false)
        ->call('selectMessage', $message->id)
        ->assertSee('Provider timeout', false);
});

test('whatsapp history detail panel shows payload summary', function () {
    [$message, , $owner] = whatsappHistoryFixture();

    Livewire::actingAs($owner)
        ->test(WhatsappHistoryPage::class)
        ->call('selectMessage', $message->id)
        ->assertSet('selectedMessageId', $message->id)
        ->assertSee(__('whatsapp.history.payload_title'), false)
        ->assertSee('row_count', false);
});

test('owner cannot select whatsapp message from another center', function () {
    [$message, , $owner] = whatsappHistoryFixture();

    $otherCenter = createTestCenter($owner->organization, ['code' => 'OTHER-'.uniqid()]);
    setOwnerActiveCenter($owner, $otherCenter);

    Livewire::actingAs($owner)
        ->test(WhatsappHistoryPage::class)
        ->call('selectMessage', $message->id)
        ->assertSet('selectedMessageId', null);
});

test('whatsapp history service maps row labels', function () {
    [$message, , $owner] = whatsappHistoryFixture();

    test()->actingAs($owner);

    $row = app(WhatsappHistoryService::class)->toRow($message->fresh());

    expect($row->eventTypeLabel)->toBe(__('whatsapp.event_type.import_success'))
        ->and($row->statusLabel)->toBe(__('whatsapp.status.sent'));
});

test('manager cannot access whatsapp history page', function () {
    [, , , $manager] = whatsappHistoryFixture();

    $this->actingAs($manager)
        ->get(route('whatsapp-history.index'))
        ->assertForbidden();
});
