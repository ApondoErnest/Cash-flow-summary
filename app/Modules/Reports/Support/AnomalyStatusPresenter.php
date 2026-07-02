<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

use App\Modules\Reports\Enums\AnomalyType;
use App\Modules\Reports\Models\Anomaly;

final class AnomalyStatusPresenter
{
    /**
     * @return array{label: string, variant: string}
     */
    public static function resolutionBadge(?Anomaly $anomaly): array
    {
        if ($anomaly?->resolved_at !== null) {
            return [
                'label' => __('anomalies.resolution.resolved'),
                'variant' => 'success',
            ];
        }

        return [
            'label' => __('anomalies.resolution.open'),
            'variant' => 'warning',
        ];
    }

    /**
     * @return array{label: string, variant: string}
     */
    public static function typeBadge(AnomalyType|string $type): array
    {
        $value = $type instanceof AnomalyType ? $type : AnomalyType::tryFrom($type);

        return match ($value) {
            AnomalyType::ProbableDuplicate => [
                'label' => __('anomalies.types.probable_duplicate'),
                'variant' => 'warning',
            ],
            AnomalyType::ReconciliationFailure => [
                'label' => __('anomalies.types.reconciliation_failure'),
                'variant' => 'error',
            ],
            default => [
                'label' => is_string($type) ? $type : $type->value,
                'variant' => 'neutral',
            ],
        };
    }
}
