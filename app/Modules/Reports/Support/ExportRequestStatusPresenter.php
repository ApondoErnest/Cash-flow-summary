<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

use App\Modules\Reports\Enums\ExportRequestStatus;
use App\Modules\Reports\Models\ExportRequest;

final class ExportRequestStatusPresenter
{
    /**
     * @return array{label: string, variant: string}
     */
    public static function badge(ExportRequest $export): array
    {
        if ($export->status === ExportRequestStatus::Completed && $export->isExpired()) {
            return [
                'label' => __('reports.export.statuses.expired'),
                'variant' => 'neutral',
            ];
        }

        return match ($export->status) {
            ExportRequestStatus::Pending => [
                'label' => __('reports.export.statuses.pending'),
                'variant' => 'info',
            ],
            ExportRequestStatus::Processing => [
                'label' => __('reports.export.statuses.processing'),
                'variant' => 'info',
            ],
            ExportRequestStatus::Completed => [
                'label' => __('reports.export.statuses.completed'),
                'variant' => 'success',
            ],
            ExportRequestStatus::Failed => [
                'label' => __('reports.export.statuses.failed'),
                'variant' => 'error',
            ],
            ExportRequestStatus::Expired => [
                'label' => __('reports.export.statuses.expired'),
                'variant' => 'neutral',
            ],
        };
    }
}
