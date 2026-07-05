<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Support;

final readonly class WhatsAppSendResult
{
    public function __construct(
        public string $providerMessageId,
    ) {}
}
