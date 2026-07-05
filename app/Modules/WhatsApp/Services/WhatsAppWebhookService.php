<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Modules\Settings\Services\SettingsService;
use App\Modules\WhatsApp\Enums\WhatsappMessageStatus;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Modules\WhatsApp\Models\WhatsappWebhookEvent;
use Illuminate\Support\Carbon;

final class WhatsAppWebhookService
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {}

    public function webhooksEnabled(): bool
    {
        return $this->settingsService->anyWhatsAppWebhooksEnabled();
    }

    public function verifySubscription(string $mode, string $verifyToken, string $challenge): ?string
    {
        if (! $this->webhooksEnabled()) {
            return null;
        }

        if ($mode !== 'subscribe') {
            return null;
        }

        if ($this->settingsService->findOrganizationIdByWebhookVerifyToken($verifyToken) === null) {
            return null;
        }

        return $challenge;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function processPayload(array $payload): void
    {
        if (! $this->webhooksEnabled()) {
            return;
        }

        foreach ($this->extractStatusUpdates($payload) as $update) {
            $this->processStatusUpdate($update);
        }
    }

    /**
     * @param  array{
     *     provider_event_id: string,
     *     phone_number_id: string,
     *     provider_message_id: string,
     *     status: string,
     *     timestamp: int,
     *     error_reason: string|null,
     *     raw_status: array<string, mixed>,
     * }  $update
     */
    private function processStatusUpdate(array $update): void
    {
        $organizationId = $this->settingsService->findOrganizationIdByWhatsAppPhoneNumberId($update['phone_number_id']);

        if ($organizationId === null || ! $this->settingsService->whatsAppWebhooksEnabled($organizationId)) {
            return;
        }

        /** @var WhatsappWebhookEvent $event */
        $event = WhatsappWebhookEvent::query()->firstOrCreate(
            ['provider_event_id' => $update['provider_event_id']],
            ['payload' => $update['raw_status']],
        );

        if ($event->processed_at !== null) {
            return;
        }

        $message = WhatsappMessage::query()
            ->withoutCenterScope()
            ->where('provider_message_id', $update['provider_message_id'])
            ->first();

        if ($message !== null) {
            $this->applyDeliveryStatus($message, $update);
        }

        $event->forceFill(['processed_at' => now()])->save();
    }

    /**
     * @param  array{
     *     provider_event_id: string,
     *     phone_number_id: string,
     *     provider_message_id: string,
     *     status: string,
     *     timestamp: int,
     *     error_reason: string|null,
     *     raw_status: array<string, mixed>,
     * }  $update
     */
    private function applyDeliveryStatus(WhatsappMessage $message, array $update): void
    {
        $occurredAt = Carbon::createFromTimestamp($update['timestamp']);

        match ($update['status']) {
            'delivered' => $this->markDelivered($message, $occurredAt),
            'read' => $this->markRead($message, $occurredAt),
            'failed' => $this->markFailed($message, $update['error_reason']),
            default => null,
        };
    }

    private function markDelivered(WhatsappMessage $message, Carbon $occurredAt): void
    {
        if (in_array($message->status, [WhatsappMessageStatus::Read, WhatsappMessageStatus::Failed], true)) {
            return;
        }

        $message->forceFill([
            'status' => WhatsappMessageStatus::Delivered,
            'delivered_at' => $message->delivered_at ?? $occurredAt,
        ])->save();
    }

    private function markRead(WhatsappMessage $message, Carbon $occurredAt): void
    {
        if ($message->status === WhatsappMessageStatus::Failed) {
            return;
        }

        $message->forceFill([
            'status' => WhatsappMessageStatus::Read,
            'read_at' => $message->read_at ?? $occurredAt,
            'delivered_at' => $message->delivered_at ?? $occurredAt,
        ])->save();
    }

    private function markFailed(WhatsappMessage $message, ?string $errorReason): void
    {
        if ($message->status === WhatsappMessageStatus::Read) {
            return;
        }

        $message->forceFill([
            'status' => WhatsappMessageStatus::Failed,
            'error_reason' => $errorReason ?? $message->error_reason ?? __('whatsapp.errors.webhook_delivery_failed'),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{
     *     provider_event_id: string,
     *     phone_number_id: string,
     *     provider_message_id: string,
     *     status: string,
     *     timestamp: int,
     *     error_reason: string|null,
     *     raw_status: array<string, mixed>,
     * }>
     */
    private function extractStatusUpdates(array $payload): array
    {
        $updates = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $value = $change['value'] ?? null;

                if (! is_array($value)) {
                    continue;
                }

                $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');

                foreach ($value['statuses'] ?? [] as $status) {
                    if (! is_array($status)) {
                        continue;
                    }

                    $providerMessageId = (string) ($status['id'] ?? '');
                    $deliveryStatus = (string) ($status['status'] ?? '');
                    $timestamp = (int) ($status['timestamp'] ?? 0);

                    if ($providerMessageId === '' || $deliveryStatus === '' || $timestamp <= 0) {
                        continue;
                    }

                    $updates[] = [
                        'provider_event_id' => hash('sha256', implode('|', [
                            $providerMessageId,
                            $deliveryStatus,
                            (string) $timestamp,
                        ])),
                        'phone_number_id' => $phoneNumberId,
                        'provider_message_id' => $providerMessageId,
                        'status' => $deliveryStatus,
                        'timestamp' => $timestamp,
                        'error_reason' => $this->extractErrorReason($status),
                        'raw_status' => $status,
                    ];
                }
            }
        }

        return $updates;
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function extractErrorReason(array $status): ?string
    {
        $errors = $status['errors'] ?? null;

        if (! is_array($errors) || $errors === []) {
            return null;
        }

        $first = $errors[0] ?? null;

        if (! is_array($first)) {
            return null;
        }

        $message = trim((string) ($first['message'] ?? $first['title'] ?? ''));

        return $message !== '' ? $message : null;
    }
}
