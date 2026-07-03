<?php

declare(strict_types=1);

namespace App\Modules\Settings\Support;

final readonly class WhatsAppSettingsData
{
    public function __construct(
        public ?string $ownerPhone,
        public ?string $phoneNumberId,
        public bool $accessTokenConfigured,
        public bool $webhookVerifyTokenConfigured,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->ownerPhone)
            && filled($this->phoneNumberId)
            && $this->accessTokenConfigured
            && $this->webhookVerifyTokenConfigured;
    }
}
