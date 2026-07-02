<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Support;

final readonly class WhatsappHistoryRow
{
    public function __construct(
        public int $id,
        public string $eventTypeLabel,
        public string $statusLabel,
        public string $statusVariant,
        public string $recipientPhone,
        public string $sentAt,
        public ?string $importFilename,
    ) {}
}
