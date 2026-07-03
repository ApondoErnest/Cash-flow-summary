<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Support;

final class CsvParseResult
{
    /**
     * @param  list<ParsedCsvRow>  $invalidRows
     */
    public function __construct(
        public readonly CsvParseSummary $summary,
        public readonly array $invalidRows = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toValidationPayload(): array
    {
        return [
            'parsing' => [
                'valid' => true,
                'row_stats' => $this->summary->toRowStats(),
                'actual_period' => $this->summary->toActualPeriod(),
            ],
        ];
    }
}
