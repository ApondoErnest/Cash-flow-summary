<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Support;

use App\Modules\WhatsApp\Models\WhatsappMessage;

final readonly class WhatsAppTestSendResult
{
    /**
     * @param  list<WhatsappMessage>  $sent
     * @param  list<array{phone: string, error: string}>  $failed
     */
    public function __construct(
        public array $sent,
        public array $failed,
    ) {}

    public function allSucceeded(): bool
    {
        return $this->failed === [] && $this->sent !== [];
    }

    public function allFailed(): bool
    {
        return $this->sent === [] && $this->failed !== [];
    }

    public function hasPartialSuccess(): bool
    {
        return $this->sent !== [] && $this->failed !== [];
    }

    public function lastSentMessage(): ?WhatsappMessage
    {
        if ($this->sent === []) {
            return null;
        }

        return $this->sent[array_key_last($this->sent)];
    }
}
