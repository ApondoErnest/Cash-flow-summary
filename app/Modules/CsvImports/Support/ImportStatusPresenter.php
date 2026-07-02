<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Support;

use App\Modules\CsvImports\Enums\ImportStatus;

final class ImportStatusPresenter
{
    /**
     * @return array{label: string, variant: string}
     */
    public static function badge(ImportStatus $status): array
    {
        return match ($status) {
            ImportStatus::Completed => [
                'label' => __('csv_import.result.status.completed'),
                'variant' => 'success',
            ],
            ImportStatus::CompletedWithDuplicates => [
                'label' => __('csv_import.result.status.completed_with_duplicates'),
                'variant' => 'warning',
            ],
            ImportStatus::CompletedWithWarnings => [
                'label' => __('csv_import.result.status.completed_with_warnings'),
                'variant' => 'warning',
            ],
            ImportStatus::AwaitingOwnerApproval => [
                'label' => __('csv_import.result.status.awaiting_owner_approval'),
                'variant' => 'warning',
            ],
            ImportStatus::ExactFileDuplicate => [
                'label' => __('csv_import.result.status.exact_file_duplicate'),
                'variant' => 'warning',
            ],
            ImportStatus::Failed => [
                'label' => __('csv_import.result.status.failed'),
                'variant' => 'error',
            ],
            ImportStatus::Processing => [
                'label' => __('csv_import.result.status.processing'),
                'variant' => 'info',
            ],
            ImportStatus::Cancelled => [
                'label' => __('csv_import.result.status.cancelled'),
                'variant' => 'error',
            ],
        };
    }

    public static function headline(ImportStatus $status): string
    {
        return match ($status) {
            ImportStatus::Completed => __('csv_import.result.headline.completed'),
            ImportStatus::CompletedWithDuplicates => __('csv_import.result.headline.completed_with_duplicates'),
            ImportStatus::CompletedWithWarnings => __('csv_import.result.headline.completed_with_warnings'),
            ImportStatus::AwaitingOwnerApproval => __('csv_import.result.headline.awaiting_owner_approval'),
            ImportStatus::ExactFileDuplicate => __('csv_import.result.headline.exact_file_duplicate'),
            ImportStatus::Failed => __('csv_import.result.headline.failed'),
            ImportStatus::Processing => __('csv_import.result.headline.processing'),
            ImportStatus::Cancelled => __('csv_import.result.headline.cancelled'),
        };
    }
}
