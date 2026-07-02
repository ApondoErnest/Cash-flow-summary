<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Support;

final class ReconciliationResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public readonly FooterSummary $footer,
        public readonly ParsedTotals $parsed,
        public readonly bool $countMatches,
        public readonly bool $htMatches,
        public readonly bool $vatMatches,
        public readonly bool $ttcMatches,
        public readonly array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->countMatches
            && $this->htMatches
            && $this->vatMatches
            && $this->ttcMatches
            && $this->errors === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toValidationPayload(): array
    {
        return [
            'reconciliation' => [
                'valid' => $this->isValid(),
                'count' => [
                    'passed' => $this->countMatches,
                    'footer' => $this->footer->count,
                    'parsed' => $this->parsed->count,
                ],
                'ht' => [
                    'passed' => $this->htMatches,
                    'footer' => $this->footer->ht,
                    'parsed' => $this->parsed->ht,
                ],
                'vat' => [
                    'passed' => $this->vatMatches,
                    'footer' => $this->footer->vat,
                    'parsed' => $this->parsed->vat,
                ],
                'ttc' => [
                    'passed' => $this->ttcMatches,
                    'footer' => $this->footer->ttc,
                    'parsed' => $this->parsed->ttc,
                ],
                'errors' => $this->errors,
            ],
        ];
    }
}
