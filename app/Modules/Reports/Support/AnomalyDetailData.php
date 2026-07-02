<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

final readonly class AnomalyDetailData
{
    /**
     * @param  list<array{label: string, value: string}>  $metadataRows
     */
    public function __construct(
        public int $id,
        public string $typeLabel,
        public string $typeVariant,
        public string $description,
        public string $resolutionLabel,
        public string $resolutionVariant,
        public string $detectedAt,
        public ?string $resolvedAt,
        public ?int $importId,
        public ?string $importFilename,
        public array $metadataRows,
        public bool $canResolve,
    ) {}
}
