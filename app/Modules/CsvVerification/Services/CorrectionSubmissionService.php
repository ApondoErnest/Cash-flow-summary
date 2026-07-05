<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Services;

use App\Models\User;
use App\Modules\AuditLogging\Services\AuditLogger;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvVerification\Enums\ImportMode;
use Illuminate\Auth\Access\AuthorizationException;

final class CorrectionSubmissionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

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

        $this->auditLogger->record(
            event: 'correction.submitted',
            user: $user,
            centerId: (int) $import->center_id,
            resourceType: Import::class,
            resourceId: (int) $import->id,
            newValues: [
                'import_id' => $import->id,
                'filename' => $import->original_filename,
                'status' => $import->status->value,
            ],
        );
    }
}
