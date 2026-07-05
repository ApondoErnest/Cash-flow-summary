<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Exceptions;

use RuntimeException;

final class WhatsAppApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $response
     */
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?array $response = null,
    ) {
        parent::__construct($message);
    }
}
