<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Support;

final readonly class ImportDetailData
{
    /**
     * @param  list<ImportDayComparisonRow>  $dayComparisons
     */
    public function __construct(
        public ImportResultData $result,
        public string $uploadedByName,
        public ?string $completedAt,
        public string $fileSizeLabel,
        public int $errorCount,
        public array $dayComparisons,
    ) {}
}
