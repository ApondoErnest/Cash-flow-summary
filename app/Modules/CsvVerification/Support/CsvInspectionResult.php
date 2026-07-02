<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Support;

final class CsvInspectionResult
{
    /**
     * @param  list<string>  $headers
     * @param  list<string>  $normalizedHeaders
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly string $encoding,
        public readonly bool $hasBom,
        public readonly string $delimiter,
        public readonly ?string $language,
        public readonly array $headers,
        public readonly array $normalizedHeaders,
        public readonly int $columnCount,
        public readonly int $csvFormatVersionId,
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toValidationPayload(): array
    {
        return [
            'inspection' => [
                'valid' => $this->isValid(),
                'encoding' => $this->encoding,
                'has_bom' => $this->hasBom,
                'delimiter' => $this->delimiter,
                'language' => $this->language,
                'column_count' => $this->columnCount,
                'headers' => $this->headers,
                'errors' => $this->errors,
                'warnings' => $this->warnings,
            ],
        ];
    }
}
