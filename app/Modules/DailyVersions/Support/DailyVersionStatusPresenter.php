<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Support;

use App\Modules\DailyVersions\Enums\DailyVersionStatus;

final class DailyVersionStatusPresenter
{
    /**
     * @return array{label: string, variant: string}
     */
    public static function badge(DailyVersionStatus $status): array
    {
        return match ($status) {
            DailyVersionStatus::Proposed => [
                'label' => __('daily_versions.status.proposed'),
                'variant' => 'warning',
            ],
            DailyVersionStatus::Active => [
                'label' => __('daily_versions.status.active'),
                'variant' => 'success',
            ],
            DailyVersionStatus::Superseded => [
                'label' => __('daily_versions.status.superseded'),
                'variant' => 'neutral',
            ],
            DailyVersionStatus::Rejected => [
                'label' => __('daily_versions.status.rejected'),
                'variant' => 'error',
            ],
            DailyVersionStatus::Invalid => [
                'label' => __('daily_versions.status.invalid'),
                'variant' => 'error',
            ],
        };
    }
}
