<?php

declare(strict_types=1);

namespace App\Modules\Normalization\Support;

use App\Modules\Normalization\NormalizationPolicy;

final class CanonicalRecord
{
    public function __construct(
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
        public readonly string $normalizationPolicyVersion = NormalizationPolicy::VERSION,
    ) {}

    /**
     * @param  array<string, int|string|null>  $fields
     */
    public static function fromCanonicalValues(
        array $fields,
        string $normalizationPolicyVersion = NormalizationPolicy::VERSION,
    ): self {
        return new self(
            registrationDate: isset($fields['registration_date']) ? (string) $fields['registration_date'] : null,
            registrationTime: isset($fields['registration_time']) ? (string) $fields['registration_time'] : null,
            completionDate: array_key_exists('completion_date', $fields) && $fields['completion_date'] !== null
                ? (string) $fields['completion_date']
                : null,
            customerName: (string) ($fields['customer_name'] ?? ''),
            categoryCode: (string) ($fields['category_code'] ?? ''),
            inspectionTypeCode: (string) ($fields['inspection_type_code'] ?? ''),
            licencePlate: (string) ($fields['licence_plate'] ?? ''),
            netAmount: isset($fields['net_amount']) ? (int) $fields['net_amount'] : null,
            vatAmount: isset($fields['vat_amount']) ? (int) $fields['vat_amount'] : null,
            grossAmount: isset($fields['gross_amount']) ? (int) $fields['gross_amount'] : null,
            normalizationPolicyVersion: $normalizationPolicyVersion,
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function canonicalFields(): array
    {
        return [
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
        ];
    }

    public function exactCanonicalHash(): string
    {
        $payload = json_encode(
            $this->canonicalFields(),
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ).$this->normalizationPolicyVersion;

        return hash('sha256', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'normalization_policy_version' => $this->normalizationPolicyVersion,
            'canonical_fields' => $this->canonicalFields(),
            'exact_canonical_hash' => $this->exactCanonicalHash(),
        ];
    }
}
