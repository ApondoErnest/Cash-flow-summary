<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

final readonly class ExportListRow
{
    public function __construct(
        public int $id,
        public string $formatLabel,
        public string $periodLabel,
        public string $statusLabel,
        public string $statusVariant,
        public ?string $downloadUrl,
        public bool $isInProgress,
        public string $requestedAt,
        public ?string $expiresAt,
    ) {}
}
