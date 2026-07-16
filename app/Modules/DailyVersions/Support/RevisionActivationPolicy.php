<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Support;

use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\WhatsApp\Support\WhatsAppTimezone;
use Illuminate\Support\Carbon;

final class RevisionActivationPolicy
{
    public function shouldAutoActivateRevision(Import $import, string $businessDate, ?Carbon $referenceMoment = null): bool
    {
        if ($import->import_mode === ImportMode::Correction) {
            return false;
        }

        $import->loadMissing('center.organization');

        $timezone = WhatsAppTimezone::forCenter($import->center);
        $today = ($referenceMoment ?? now())->timezone($timezone)->toDateString();

        return $today === $businessDate;
    }
}
