<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Support;

final readonly class VerificationSummaryData
{
    /**
     * @param  list<VerificationSummaryCheck>  $checks
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $filename,
        public string $centerName,
        public string $sourceLanguage,
        public ?string $reportedPeriod,
        public ?string $actualPeriod,
        public string $footerCount,
        public string $footerHt,
        public string $footerVat,
        public string $footerTtc,
        public array $checks,
        public int $completed,
        public int $unfinished,
        public int $revenueGenerating,
        public int $zeroValue,
        public int $invalidRows,
        public int $exactDuplicates,
        public int $newUnique,
        public int $probableDuplicates,
        public array $warnings,
        public bool $canImport,
    ) {}
}
