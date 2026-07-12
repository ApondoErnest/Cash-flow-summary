<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Enums\WhatsappMessageStatus;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Modules\WhatsApp\Support\WhatsappHistoryDetailData;
use App\Modules\WhatsApp\Support\WhatsappHistoryRow;
use App\Modules\WhatsApp\Support\WhatsappMessagePresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class WhatsappHistoryService
{
    /**
     * @param  array{status?: string|null, event_type?: string|null, from?: string|null, to?: string|null}  $filters
     * @return LengthAwarePaginator<int, WhatsappMessage>
     */
    public function paginateForActiveCenter(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = WhatsappMessage::query()
            ->with(['import:id,original_filename'])
            ->orderByDesc('created_at');

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        }

        if (($filters['event_type'] ?? '') !== '') {
            $query->where('event_type', $filters['event_type']);
        }

        if (($filters['from'] ?? '') !== '') {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (($filters['to'] ?? '') !== '') {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->paginate($perPage);
    }

    public function toRow(WhatsappMessage $message): WhatsappHistoryRow
    {
        $message->loadMissing(['import:id,original_filename']);
        $statusBadge = WhatsappMessagePresenter::statusBadge($message->status);

        return new WhatsappHistoryRow(
            id: $message->id,
            eventTypeLabel: WhatsappMessagePresenter::eventTypeLabel($message->event_type),
            statusLabel: $statusBadge['label'],
            statusVariant: $statusBadge['variant'],
            recipientPhone: $message->recipient_phone,
            sentAt: $this->formatTimestamp($message->sent_at ?? $message->created_at),
            importFilename: $message->import?->original_filename,
        );
    }

    public function toDetail(WhatsappMessage $message): WhatsappHistoryDetailData
    {
        $message->loadMissing(['import:id,original_filename']);
        $statusBadge = WhatsappMessagePresenter::statusBadge($message->status);

        return new WhatsappHistoryDetailData(
            id: $message->id,
            eventTypeLabel: WhatsappMessagePresenter::eventTypeLabel($message->event_type),
            statusLabel: $statusBadge['label'],
            statusVariant: $statusBadge['variant'],
            recipientPhone: $message->recipient_phone,
            templateName: $message->template_name,
            providerMessageId: $message->provider_message_id,
            errorReason: $message->error_reason,
            retryCount: $message->retry_count,
            createdAt: $this->formatTimestamp($message->created_at),
            sentAt: $message->sent_at !== null ? $this->formatTimestamp($message->sent_at) : null,
            deliveredAt: $message->delivered_at !== null ? $this->formatTimestamp($message->delivered_at) : null,
            readAt: $message->read_at !== null ? $this->formatTimestamp($message->read_at) : null,
            importId: $message->import_id,
            importFilename: $message->import?->original_filename,
            payloadRows: $this->payloadRows($message->payload_summary ?? []),
            canResend: $message->status === WhatsappMessageStatus::Failed,
        );
    }

    /**
     * @return list<WhatsappMessageStatus>
     */
    public function filterableStatuses(): array
    {
        return WhatsappMessageStatus::cases();
    }

    /**
     * @return list<string>
     */
    public function filterableEventTypes(): array
    {
        return [
            'daily_summary',
            'weekly_summary',
            'monthly_summary',
            'yearly_summary',
            'import_success',
            'import_with_duplicates',
            'duplicate_only',
            'revision_pending',
            'revision_approved',
            'reconciliation_mismatch',
            'missing_submission',
            'delivery_failure',
            'daily_summary',
            'historical_import',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{label: string, value: string}>
     */
    private function payloadRows(array $payload): array
    {
        $rows = [];

        foreach ($payload as $key => $value) {
            $rows[] = [
                'label' => (string) $key,
                'value' => is_scalar($value) || $value === null
                    ? (string) $value
                    : json_encode($value, JSON_THROW_ON_ERROR),
            ];
        }

        return $rows;
    }

    private function formatTimestamp(?Carbon $timestamp): string
    {
        return \App\Support\Locale\LocalizedDateTime::dateTime($timestamp);
    }
}
