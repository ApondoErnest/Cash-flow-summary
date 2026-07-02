<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Support;

final readonly class RecordDetailData
{
    public function __construct(
        public int $id,
        public string $registrationDate,
        public string $registrationTime,
        public ?string $completionDate,
        public string $customerName,
        public string $licencePlate,
        public string $categoryCode,
        public string $inspectionTypeCode,
        public string $netAmount,
        public string $vatAmount,
        public string $grossAmount,
        public string $completionStatusLabel,
        public string $completionStatusVariant,
        public string $financialStatusLabel,
        public string $financialStatusVariant,
        public ?string $firstSeenAt,
        public ?int $firstImportId,
        public ?string $firstImportFilename,
        public string $normalizationPolicyVersion,
    ) {}
}
