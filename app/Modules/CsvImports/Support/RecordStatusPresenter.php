<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Support;

use App\Modules\CsvImports\Enums\CompletionStatus;
use App\Modules\CsvImports\Enums\FinancialStatus;

final class RecordStatusPresenter
{
    /**
     * @return array{label: string, variant: string}
     */
    public static function completion(CompletionStatus $status): array
    {
        return match ($status) {
            CompletionStatus::Completed => [
                'label' => __('records.status.completion.completed'),
                'variant' => 'success',
            ],
            CompletionStatus::Unfinished => [
                'label' => __('records.status.completion.unfinished'),
                'variant' => 'warning',
            ],
        };
    }

    /**
     * @return array{label: string, variant: string}
     */
    public static function financial(FinancialStatus $status): array
    {
        return match ($status) {
            FinancialStatus::Revenue => [
                'label' => __('records.status.financial.revenue'),
                'variant' => 'success',
            ],
            FinancialStatus::ZeroValue => [
                'label' => __('records.status.financial.zero_value'),
                'variant' => 'neutral',
            ],
        };
    }
}
