<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Support;

use App\Modules\CsvVerification\Enums\CsvRowStatus;

final class CsvParseSummary
{
    public int $totalRows = 0;

    public int $completed = 0;

    public int $unfinished = 0;

    public int $zero = 0;

    public int $invalid = 0;

    public ?string $earliestRegistrationDate = null;

    public ?string $latestRegistrationDate = null;

    public function add(ParsedCsvRow $row): void
    {
        $this->totalRows++;

        match ($row->status) {
            CsvRowStatus::Completed => $this->completed++,
            CsvRowStatus::Unfinished => $this->unfinished++,
            CsvRowStatus::Invalid => $this->invalid++,
        };

        if ($row->status === CsvRowStatus::Completed
            && $row->netAmount === 0
            && $row->vatAmount === 0
            && $row->grossAmount === 0) {
            $this->zero++;
        }

        if ($row->status !== CsvRowStatus::Invalid && $row->registrationDate !== null) {
            $this->earliestRegistrationDate = $this->earliestRegistrationDate === null
                ? $row->registrationDate
                : min($this->earliestRegistrationDate, $row->registrationDate);

            $this->latestRegistrationDate = $this->latestRegistrationDate === null
                ? $row->registrationDate
                : max($this->latestRegistrationDate, $row->registrationDate);
        }
    }

    /**
     * @return array{start: string, end: string}|null
     */
    public function toActualPeriod(): ?array
    {
        if ($this->earliestRegistrationDate === null || $this->latestRegistrationDate === null) {
            return null;
        }

        return [
            'start' => $this->earliestRegistrationDate,
            'end' => $this->latestRegistrationDate,
        ];
    }

    /**
     * @return array{completed: int, unfinished: int, zero: int, invalid: int, total_rows: int}
     */
    public function toRowStats(): array
    {
        return [
            'completed' => $this->completed,
            'unfinished' => $this->unfinished,
            'zero' => $this->zero,
            'invalid' => $this->invalid,
            'total_rows' => $this->totalRows,
        ];
    }
}
