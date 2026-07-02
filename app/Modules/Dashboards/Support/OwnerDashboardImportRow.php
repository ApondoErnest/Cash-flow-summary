<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Support;

use App\Modules\CsvImports\Enums\ImportStatus;

final readonly class OwnerDashboardImportRow
{
    public function __construct(
        public int $id,
        public string $importedAt,
        public string $filename,
        public string $totalTtc,
        public ImportStatus $status,
    ) {}
}
