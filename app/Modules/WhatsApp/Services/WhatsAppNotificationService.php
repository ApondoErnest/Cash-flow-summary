<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Modules\Centers\Models\Center;
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
        private readonly WhatsAppScheduledSummaryService $scheduledSummaryService,
    ) {}

    public function scheduledSummaryIdempotencyKey(
        WhatsappEventType $eventType,
        int $centerId,
        string $periodKey,
    ): string {
        return "{$eventType->value}:center:{$centerId}:{$periodKey}";
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?WhatsappMessage
    {
        return WhatsappMessage::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    public function queueScheduledSummary(
        Center $center,
        WhatsappEventType $eventType,
        Carbon $referenceMoment,
    ): ?WhatsappMessage {
        if (! $eventType->isScheduledSummary()) {
            return null;
        }

        $message = $this->prepareScheduledSummary($center, $eventType, $referenceMoment);

        if ($message === null) {
            return null;
        }

        if ($message->status === WhatsappMessageStatus::Queued) {
            SendWhatsAppNotificationJob::dispatch($message->id);
        }

        return $message;
    }

    public function prepareScheduledSummary(
        Center $center,
        WhatsappEventType $eventType,
        Carbon $referenceMoment,
    ): ?WhatsappMessage {
        $organizationId = (int) $center->organization_id;

        if (! $this->settingsService->whatsAppOutboundConfigured($organizationId)) {
            return null;
        }

        $period = $this->scheduledSummaryService->periodFor($eventType, $referenceMoment);
        $idempotencyKey = $this->scheduledSummaryIdempotencyKey(
            $eventType,
            (int) $center->id,
            $period->periodKey,
        );

        $existing = $this->findByIdempotencyKey($idempotencyKey);

        if ($existing !== null) {
            return $existing;
        }

        $credentials = $this->credentialsForOrganization($organizationId);

        return $this->createQueuedMessage(
            credentials: $credentials,
            eventType: $eventType,
            idempotencyKey: $idempotencyKey,
            centerId: (int) $center->id,
            importId: null,
            payloadSummary: $this->scheduledSummaryService->buildPayloadSummary(
                $center,
                $eventType,
                $referenceMoment,
            ),
        );
    }

    /**
     * Synchronous send path used in tests and by the queue job.
     */
    public function notifyScheduledSummary(
        Center $center,
        WhatsappEventType $eventType,
        Carbon $referenceMoment,
    ): ?WhatsappMessage {
        $message = $this->prepareScheduledSummary($center, $eventType, $referenceMoment);

        if ($message === null || $message->status !== WhatsappMessageStatus::Queued) {
            return $message;
        }

        return $this->sendMessage($message);
    }

    public function resendFailedMessage(WhatsappMessage $message): ?WhatsappMessage
    {
        if ($message->status !== WhatsappMessageStatus::Failed) {
            return null;
        }

        $eventType = WhatsappEventType::from($message->event_type);

        $message->forceFill([
            'status' => WhatsappMessageStatus::Queued,
            'error_reason' => null,
            'retry_count' => 0,
            'provider_message_id' => null,
            'sent_at' => null,
            'delivered_at' => null,
            'read_at' => null,
            'template_name' => $eventType->templateName(),
        ])->save();

        SendWhatsAppNotificationJob::dispatch((int) $message->id);

        return $message->fresh();
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
    ): WhatsappMessage {
        if ($message->status !== WhatsappMessageStatus::Queued) {
            return $message;
        }

        $message->loadMissing(['center']);

        $organizationId = (int) $message->center->organization_id;
        $credentials ??= $this->credentialsForOrganization($organizationId);
        $eventType ??= WhatsappEventType::from($message->event_type);

        try {
            $result = $this->cloudApiClient->sendTemplateMessage(
                credentials: $credentials,
                recipientPhone: $message->recipient_phone,
                templateName: $eventType->templateName(),
                languageCode: $this->resolveTemplateLanguage($organizationId, $eventType),
                bodyParameters: $this->templateBodyParametersFromSummary($message),
                bodyParameterNames: $eventType->templateBodyParameterNames(),
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

    private function credentialsForOrganization(int $organizationId): WhatsAppCredentials
    {
        $credentials = $this->settingsService->whatsAppCredentials($organizationId);

        if ($credentials === null) {
            throw WhatsAppNotConfiguredException::forOrganization($organizationId);
        }

        return $credentials;
    }

    private function resolveTemplateLanguage(int $organizationId, WhatsappEventType $eventType): string
    {
        if ($eventType->usesActivitySummaryTemplate()) {
            return $this->settingsService->whatsAppTemplateLanguage($organizationId);
        }

        return $eventType->templateLanguageCode();
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
            (string) ($summary['category_summary'] ?? 'A: 0, B: 0, B1: 0, C: 0, D: 0'),
            (string) ($summary['footer_ht'] ?? '0'),
            (string) ($summary['footer_vat'] ?? '0'),
            (string) ($summary['footer_ttc'] ?? '0'),
        ];
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
                languageCode: WhatsappEventType::TestMessage->templateLanguageCode(),
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
