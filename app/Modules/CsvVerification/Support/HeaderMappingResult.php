<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Support;

final class HeaderMappingResult
{
    /**
     * @param  array<string, int>  $mapping
     * @param  list<string>  $errors
     * @param  list<string>  $unknownHeaders
     * @param  array<string, list<string>>  $suggestions
     */
    public function __construct(
        public readonly ?string $language,
        public readonly array $mapping,
        public readonly bool $isMixedLanguage = false,
        public readonly array $errors = [],
        public readonly array $unknownHeaders = [],
        public readonly array $suggestions = [],
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
            'header_mapping' => [
                'valid' => $this->isValid(),
                'language' => $this->language,
                'mapping' => $this->mapping,
                'is_mixed_language' => $this->isMixedLanguage,
                'unknown_headers' => $this->unknownHeaders,
                'suggestions' => $this->suggestions,
                'errors' => $this->errors,
            ],
        ];
    }
}
