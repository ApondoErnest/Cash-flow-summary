<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Services;

use App\Models\User;
use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvVerification\Enums\ImportMode;
use Illuminate\Auth\Access\AuthorizationException;

final class CorrectionSubmissionService
{
    public function assertModeAllowed(User $user, ImportMode $mode): void
    {
        if ($mode->canSubmit($user)) {
            return;
        }

        throw new AuthorizationException(__('csv_verification.correction.not_allowed'));
    }

    public function recordSubmission(Import $import, User $user): void
    {
        if ($import->import_mode !== ImportMode::Correction) {
            return;
        }

        AuditLog::query()->create([
            'user_id' => $user->id,
            'center_id' => $import->center_id,
            'event' => 'correction.submitted',
            'resource_type' => Import::class,
            'resource_id' => $import->id,
            'new_values' => [
                'import_id' => $import->id,
                'filename' => $import->original_filename,
                'status' => $import->status->value,
            ],
        ]);
    }
}
