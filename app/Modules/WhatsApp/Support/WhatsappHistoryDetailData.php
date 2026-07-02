<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Support;

final readonly class WhatsappHistoryDetailData
{
    /**
     * @param  list<array{label: string, value: string}>  $payloadRows
     */
    public function __construct(
        public int $id,
        public string $eventTypeLabel,
        public string $statusLabel,
        public string $statusVariant,
        public string $recipientPhone,
        public ?string $templateName,
        public ?string $providerMessageId,
        public ?string $errorReason,
        public int $retryCount,
        public string $createdAt,
        public ?string $sentAt,
        public ?string $deliveredAt,
        public ?string $readAt,
        public ?int $importId,
        public ?string $importFilename,
        public array $payloadRows,
    ) {}
}
