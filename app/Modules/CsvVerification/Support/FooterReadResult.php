<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Support;

final class FooterReadResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public readonly ?FooterSummary $summary,
        public readonly array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->summary !== null && $this->errors === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toValidationPayload(): array
    {
        return [
            'footer' => [
                'valid' => $this->isValid(),
                'summary' => $this->summary?->toArray(),
                'errors' => $this->errors,
            ],
        ];
    }
}
