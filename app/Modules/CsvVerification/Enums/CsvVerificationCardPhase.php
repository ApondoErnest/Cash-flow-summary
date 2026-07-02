<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Enums;

enum CsvVerificationCardPhase: string
{
    case Empty = 'empty';
    case FileSelected = 'file_selected';
    case Verifying = 'verifying';
    case Ready = 'ready';
    case Importing = 'importing';
    case Invalid = 'invalid';

    public function label(): string
    {
        return match ($this) {
            self::Empty => __('csv_verification.card.phase.empty'),
            self::FileSelected => __('csv_verification.card.phase.file_selected'),
            self::Verifying => __('csv_verification.card.phase.verifying'),
            self::Ready => __('csv_verification.card.phase.ready'),
            self::Importing => __('csv_verification.card.phase.importing'),
            self::Invalid => __('csv_verification.card.phase.invalid'),
        };
    }
}
