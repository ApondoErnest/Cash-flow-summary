<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

final readonly class AnomalyListRow
{
    public function __construct(
        public int $id,
        public string $typeLabel,
        public string $typeVariant,
        public string $description,
        public string $resolutionLabel,
        public string $resolutionVariant,
        public string $detectedAt,
        public ?string $importFilename,
    ) {}
}
