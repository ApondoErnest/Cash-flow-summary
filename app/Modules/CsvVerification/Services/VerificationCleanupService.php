<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Services;

use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\CsvVerification\Support\VerificationCleanupResult;
use Illuminate\Support\Facades\Storage;

final class VerificationCleanupService
{
    /**
     * @var list<VerificationStatus>
     */
    private const EXPIRABLE_STATUSES = [
        VerificationStatus::Pending,
        VerificationStatus::Processing,
        VerificationStatus::Ready,
        VerificationStatus::Failed,
    ];

    public function run(): VerificationCleanupResult
    {
        $expired = 0;
        $filesDeleted = 0;
        $batchSize = max(1, (int) config('csv_verification.cleanup_batch_size', 100));

        ImportVerification::query()
            ->where('expires_at', '<=', now())
            ->whereIn('status', array_map(
                static fn (VerificationStatus $status): string => $status->value,
                self::EXPIRABLE_STATUSES,
            ))
            ->orderBy('id')
            ->chunkById($batchSize, function ($verifications) use (&$expired, &$filesDeleted): void {
                foreach ($verifications as $verification) {
                    if ($this->deleteTempFile($verification)) {
                        $filesDeleted++;
                    }

                    $verification->update([
                        'status' => VerificationStatus::Expired,
                    ]);

                    $expired++;
                }
            });

        $orphanFilesDeleted = $this->deleteOrphanedRejectedFiles($batchSize);

        return new VerificationCleanupResult(
            expired: $expired,
            filesDeleted: $filesDeleted,
            orphanFilesDeleted: $orphanFilesDeleted,
        );
    }

    public function deleteTempFile(ImportVerification $verification): bool
    {
        if ($verification->temp_storage_path === '') {
            return false;
        }

        $disk = (string) config('csv_verification.temp_disk', 'local');

        if (! Storage::disk($disk)->exists($verification->temp_storage_path)) {
            return false;
        }

        Storage::disk($disk)->delete($verification->temp_storage_path);

        return true;
    }

    private function deleteOrphanedRejectedFiles(int $batchSize): int
    {
        $deleted = 0;

        ImportVerification::query()
            ->where('status', VerificationStatus::Rejected)
            ->whereNotNull('temp_storage_path')
            ->orderBy('id')
            ->chunkById($batchSize, function ($verifications) use (&$deleted): void {
                foreach ($verifications as $verification) {
                    if ($this->deleteTempFile($verification)) {
                        $deleted++;
                    }
                }
            });

        return $deleted;
    }
}
