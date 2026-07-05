<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Support;

final readonly class WhatsAppCredentials
{
    public function __construct(
        public string $ownerPhone,
        public string $phoneNumberId,
        public string $accessToken,
    ) {}
}
