<?php

declare(strict_types=1);

namespace App\Modules\Normalization\Services;

use App\Modules\Normalization\NormalizationPolicy;
use App\Modules\Normalization\Support\CanonicalRecord;

final class SimilarityFingerprintService
{
    public function fingerprint(CanonicalRecord $record): string
    {
        $fields = [
            'registration_date' => $record->registrationDate,
            'registration_time' => $record->registrationTime,
            'completion_date' => $record->completionDate,
            'licence_plate' => $record->licencePlate,
            'category_code' => $record->categoryCode,
            'inspection_type_code' => $record->inspectionTypeCode,
            'net_amount' => $record->netAmount,
            'vat_amount' => $record->vatAmount,
            'gross_amount' => $record->grossAmount,
        ];

        $payload = json_encode(
            $fields,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ).NormalizationPolicy::VERSION.':similarity';

        return hash('sha256', $payload);
    }
}
