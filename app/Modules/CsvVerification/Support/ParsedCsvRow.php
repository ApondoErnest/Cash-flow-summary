<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Support;

use App\Modules\CsvVerification\Enums\CsvRowStatus;

final class ParsedCsvRow
{
    /**
     * @param  array<string, string>  $rawValues
     * @param  list<string>  $errors
     */
    public function __construct(
        public readonly int $rowNumber,
        public readonly array $rawValues,
        public readonly ?string $registrationDate,
        public readonly ?string $registrationTime,
        public readonly ?string $completionDate,
        public readonly string $customerName,
        public readonly string $categoryCode,
        public readonly string $inspectionTypeCode,
        public readonly string $licencePlate,
        public readonly ?int $netAmount,
        public readonly ?int $vatAmount,
        public readonly ?int $grossAmount,
        public readonly CsvRowStatus $status,
        public readonly array $errors = [],
    ) {}

    public function rawRowChecksum(): string
    {
        return hash('sha256', json_encode($this->rawValues, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'row_number' => $this->rowNumber,
            'raw_values' => $this->rawValues,
            'registration_date' => $this->registrationDate,
            'registration_time' => $this->registrationTime,
            'completion_date' => $this->completionDate,
            'customer_name' => $this->customerName,
            'category_code' => $this->categoryCode,
            'inspection_type_code' => $this->inspectionTypeCode,
            'licence_plate' => $this->licencePlate,
            'net_amount' => $this->netAmount,
            'vat_amount' => $this->vatAmount,
            'gross_amount' => $this->grossAmount,
            'status' => $this->status->value,
            'errors' => $this->errors,
            'raw_row_checksum' => $this->rawRowChecksum(),
        ];
    }
}
