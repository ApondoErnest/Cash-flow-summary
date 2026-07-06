<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\Dashboards\Support\DashboardMoney;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\WhatsApp\Enums\WhatsappEventType;
use App\Modules\WhatsApp\Enums\WhatsappMessageStatus;
use App\Modules\WhatsApp\Exceptions\WhatsAppApiException;
use App\Modules\WhatsApp\Exceptions\WhatsAppNotConfiguredException;
use App\Modules\WhatsApp\Jobs\SendWhatsAppNotificationJob;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Modules\WhatsApp\Support\WhatsAppCredentials;
use Illuminate\Support\Carbon;

final class WhatsAppNotificationService
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly WhatsAppCloudApiClient $cloudApiClient,
    ) {}

    public function idempotencyKey(
        WhatsappEventType $eventType,
        ?int $importId = null,
        ?int $revisionId = null,
    ): string {
        $segments = [$eventType->value];

        if ($importId !== null) {
            $segments[] = 'import:'.$importId;
        }

        if ($revisionId !== null) {
            $segments[] = 'revision:'.$revisionId;
        }

        return implode(':', $segments);
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?WhatsappMessage
    {
        return WhatsappMessage::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    public function resolveEventTypeForImport(Import $import): WhatsappEventType
    {
        if ($import->import_mode === ImportMode::Historical) {
            return WhatsappEventType::HistoricalImport;
        }

        if ($import->duplicate_within_file_count > 0 || $import->historical_duplicate_count > 0) {
            if ($import->new_master_count === 0) {
                return WhatsappEventType::DuplicateOnly;
            }

            return WhatsappEventType::ImportWithDuplicates;
        }

        return WhatsappEventType::ImportSuccess;
    }

    public function queueImportNotification(Import $import, ?WhatsappEventType $eventType = null): ?WhatsappMessage
    {
        if (! $this->shouldQueueImportNotification($import)) {
            return null;
        }

        $message = $this->prepareImportNotification($import, $eventType);

        if ($message === null) {
            return null;
        }

        if ($message->status === WhatsappMessageStatus::Queued) {
            SendWhatsAppNotificationJob::dispatch($message->id);
        }

        return $message;
    }

    public function resendFailedMessage(WhatsappMessage $message): ?WhatsappMessage
    {
        if ($message->status !== WhatsappMessageStatus::Failed) {
            return null;
        }

        $message->forceFill([
            'status' => WhatsappMessageStatus::Queued,
            'error_reason' => null,
            'retry_count' => 0,
            'provider_message_id' => null,
            'sent_at' => null,
            'delivered_at' => null,
            'read_at' => null,
        ])->save();

        SendWhatsAppNotificationJob::dispatch((int) $message->id);

        return $message->fresh();
    }

    /**
     * Synchronous send path used in tests and by the queue job.
     */
    public function notifyForImport(Import $import, ?WhatsappEventType $eventType = null): ?WhatsappMessage
    {
        if (! $this->shouldQueueImportNotification($import)) {
            return null;
        }

        $message = $this->prepareImportNotification($import, $eventType);

        if ($message === null || $message->status !== WhatsappMessageStatus::Queued) {
            return $message;
        }

        return $this->sendMessage($message);
    }

    public function prepareImportNotification(Import $import, ?WhatsappEventType $eventType = null): ?WhatsappMessage
    {
        $import->loadMissing(['center', 'uploadedBy']);

        $organizationId = (int) $import->center->organization_id;

        if (! $this->settingsService->whatsAppOutboundConfigured($organizationId)) {
            return null;
        }

        $eventType ??= $this->resolveEventTypeForImport($import);
        $idempotencyKey = $this->idempotencyKey($eventType, $import->id);

        $existing = $this->findByIdempotencyKey($idempotencyKey);

        if ($existing !== null) {
            return $existing;
        }

        $credentials = $this->credentialsForOrganization($organizationId);

        return $this->createQueuedMessage(
            credentials: $credentials,
            eventType: $eventType,
            idempotencyKey: $idempotencyKey,
            centerId: (int) $import->center_id,
            importId: (int) $import->id,
            payloadSummary: $this->buildPayloadSummaryForImport($import, $eventType),
        );
    }

    public function shouldQueueImportNotification(Import $import): bool
    {
        if (! in_array($import->status, [
            ImportStatus::Completed,
            ImportStatus::CompletedWithDuplicates,
            ImportStatus::CompletedWithWarnings,
        ], true)) {
            return false;
        }

        if ($import->import_mode !== ImportMode::Historical) {
            return true;
        }

        $import->loadMissing('importVerification');

        return $import->importVerification?->notify_owner === true;
    }

    /**
     * @param  array<string, mixed>  $payloadSummary
     */
    public function createQueuedMessage(
        WhatsAppCredentials $credentials,
        WhatsappEventType $eventType,
        string $idempotencyKey,
        ?int $centerId,
        ?int $importId,
        array $payloadSummary,
    ): WhatsappMessage {
        /** @var WhatsappMessage $message */
        $message = WhatsappMessage::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'center_id' => $centerId,
                'import_id' => $importId,
                'event_type' => $eventType->value,
                'recipient_phone' => $credentials->ownerPhone,
                'template_name' => $eventType->templateName(),
                'payload_summary' => $payloadSummary,
                'status' => WhatsappMessageStatus::Queued,
                'retry_count' => 0,
            ],
        );

        return $message;
    }

    public function sendMessage(
        WhatsappMessage $message,
        ?WhatsAppCredentials $credentials = null,
        ?WhatsappEventType $eventType = null,
        ?Import $import = null,
    ): WhatsappMessage {
        if ($message->status !== WhatsappMessageStatus::Queued) {
            return $message;
        }

        $message->loadMissing(['center']);

        $organizationId = (int) $message->center->organization_id;
        $credentials ??= $this->credentialsForOrganization($organizationId);
        $eventType ??= WhatsappEventType::from($message->event_type);

        if ($import === null && $message->import_id !== null) {
            $import = Import::query()->with(['center', 'uploadedBy'])->find($message->import_id);
        }

        try {
            $result = $this->cloudApiClient->sendTemplateMessage(
                credentials: $credentials,
                recipientPhone: $message->recipient_phone,
                templateName: $message->template_name ?? $eventType->templateName(),
                languageCode: (string) config('whatsapp.default_language', 'en'),
                bodyParameters: $import !== null
                    ? $this->templateBodyParametersForImport($import)
                    : $this->templateBodyParametersFromSummary($message),
            );

            $message->forceFill([
                'status' => WhatsappMessageStatus::Sent,
                'provider_message_id' => $result->providerMessageId,
                'error_reason' => null,
                'sent_at' => now(),
            ])->save();

            return $message->fresh();
        } catch (WhatsAppApiException $exception) {
            $message->forceFill([
                'error_reason' => $exception->getMessage(),
                'retry_count' => $message->retry_count + 1,
            ])->save();

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayloadSummaryForImport(Import $import, WhatsappEventType $eventType): array
    {
        $import->loadMissing(['center', 'uploadedBy']);

        return [
            'event_type' => $eventType->value,
            'center_name' => $import->center->name,
            'period' => $this->formatImportPeriod($import),
            'row_count' => $import->parsed_count,
            'new_unique' => $import->new_master_count,
            'duplicates_ignored' => $import->duplicate_within_file_count + $import->historical_duplicate_count,
            'footer_ht' => DashboardMoney::format($import->source_ht),
            'footer_vat' => DashboardMoney::format($import->source_vat),
            'footer_ttc' => DashboardMoney::format($import->source_ttc),
            'uploader' => $import->uploadedBy?->name,
        ];
    }

    /**
     * @return list<string>
     */
    public function templateBodyParametersForImport(Import $import): array
    {
        $import->loadMissing(['center', 'uploadedBy']);

        return [
            $import->center->name,
            $this->formatImportPeriod($import) ?? '—',
            (string) $import->parsed_count,
            DashboardMoney::format($import->source_ht),
            DashboardMoney::format($import->source_vat),
            DashboardMoney::format($import->source_ttc),
            $import->uploadedBy?->name ?? __('whatsapp.unknown_uploader'),
        ];
    }

    private function credentialsForOrganization(int $organizationId): WhatsAppCredentials
    {
        $credentials = $this->settingsService->whatsAppCredentials($organizationId);

        if ($credentials === null) {
            throw WhatsAppNotConfiguredException::forOrganization($organizationId);
        }

        return $credentials;
    }

    /**
     * @return list<string>
     */
    private function templateBodyParametersFromSummary(WhatsappMessage $message): array
    {
        $summary = $message->payload_summary ?? [];

        return [
            (string) ($summary['center_name'] ?? '—'),
            (string) ($summary['period'] ?? '—'),
            (string) ($summary['row_count'] ?? '0'),
            (string) ($summary['footer_ht'] ?? '0'),
            (string) ($summary['footer_vat'] ?? '0'),
            (string) ($summary['footer_ttc'] ?? '0'),
            (string) ($summary['uploader'] ?? __('whatsapp.unknown_uploader')),
        ];
    }

    private function formatImportPeriod(Import $import): ?string
    {
        if ($import->actual_period_start === null || $import->actual_period_end === null) {
            return null;
        }

        $start = Carbon::parse($import->actual_period_start)->format('d/m/Y');
        $end = Carbon::parse($import->actual_period_end)->format('d/m/Y');

        return $start === $end ? $start : "{$start} – {$end}";
    }

    public function sendTestMessage(int $organizationId, ?int $centerId = null): WhatsappMessage
    {
        $credentials = $this->credentialsForOrganization($organizationId);
        $idempotencyKey = 'test_message:'.$organizationId.':'.now()->format('YmdHisu');

        $message = $this->createQueuedMessage(
            credentials: $credentials,
            eventType: WhatsappEventType::TestMessage,
            idempotencyKey: $idempotencyKey,
            centerId: $centerId,
            importId: null,
            payloadSummary: [
                'event_type' => WhatsappEventType::TestMessage->value,
                'template' => WhatsappEventType::TestMessage->templateName(),
                'initiated_at' => now()->timezone(config('app.timezone'))->toIso8601String(),
            ],
        );

        try {
            $result = $this->cloudApiClient->sendTemplateMessage(
                credentials: $credentials,
                recipientPhone: $credentials->ownerPhone,
                templateName: WhatsappEventType::TestMessage->templateName(),
                languageCode: (string) config('whatsapp.test_template_language', 'en_US'),
                bodyParameters: [],
            );

            $message->forceFill([
                'status' => WhatsappMessageStatus::Sent,
                'provider_message_id' => $result->providerMessageId,
                'error_reason' => null,
                'sent_at' => now(),
            ])->save();

            return $message->fresh();
        } catch (WhatsAppApiException $exception) {
            $message->forceFill([
                'status' => WhatsappMessageStatus::Failed,
                'error_reason' => $exception->getMessage(),
                'retry_count' => 1,
            ])->save();

            throw $exception;
        }
    }
}
