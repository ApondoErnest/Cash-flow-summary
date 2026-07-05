<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Support;

final class WhatsAppWebhookSignatureVerifier
{
    public function isValid(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = config('whatsapp.app_secret');

        if (! is_string($secret) || $secret === '' || $signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        if (! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signatureHeader);
    }
}
