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
