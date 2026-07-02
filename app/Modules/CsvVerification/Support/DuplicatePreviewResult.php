<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Support;

final class DuplicatePreviewResult
{
    public function __construct(
        public readonly int $exact,
        public readonly int $probable,
        public readonly int $newUnique,
        public readonly int $normalizedRows,
    ) {}

    /**
     * @return array{exact: int, probable: int, new_unique: int}
     */
    public function toSummary(): array
    {
        return [
            'exact' => $this->exact,
            'probable' => $this->probable,
            'new_unique' => $this->newUnique,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toValidationPayload(): array
    {
        return [
            'duplicate_preview' => [
                'exact' => $this->exact,
                'probable' => $this->probable,
                'new_unique' => $this->newUnique,
                'normalized_rows' => $this->normalizedRows,
            ],
        ];
    }
}
