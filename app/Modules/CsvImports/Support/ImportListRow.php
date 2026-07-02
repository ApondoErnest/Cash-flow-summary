<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Support;

final readonly class ImportListRow
{
    public function __construct(
        public int $id,
        public string $importedAt,
        public string $filename,
        public string $importModeLabel,
        public ?string $actualPeriod,
        public string $totalTtc,
        public string $statusLabel,
        public string $statusVariant,
        public string $uploadedByName,
    ) {}
}
