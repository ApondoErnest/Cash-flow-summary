<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Support;

final readonly class ImportResultData
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $importId,
        public string $headline,
        public string $statusBadge,
        public string $statusVariant,
        public string $filename,
        public string $centerName,
        public string $importModeLabel,
        public string $sourceLanguage,
        public ?string $actualPeriod,
        public int $sourceRows,
        public int $newUnique,
        public int $duplicatesIgnored,
        public int $invalidRows,
        public int $activeDays,
        public int $unchangedDays,
        public int $revisionsPending,
        public string $footerHt,
        public string $footerVat,
        public string $footerTtc,
        public string $whatsappStatus,
        public string $whatsappVariant,
        public array $warnings,
        public bool $isExactFileDuplicate,
    ) {}
}
