<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Enums\ExportRequestStatus;
use App\Modules\Reports\Models\ExportRequest;
use App\Modules\Reports\Support\ExportCleanupResult;
use Illuminate\Support\Facades\Storage;

final class ExportCleanupService
{
    public function run(): ExportCleanupResult
    {
        $expired = 0;
        $filesDeleted = 0;
        $batchSize = max(1, (int) config('exports.cleanup_batch_size', 100));
        $disk = (string) config('exports.disk', 'local');

        ExportRequest::query()
            ->where('status', ExportRequestStatus::Completed)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById($batchSize, function ($exports) use (&$expired, &$filesDeleted, $disk): void {
                foreach ($exports as $export) {
                    if ($this->deleteStoredFile($export, $disk)) {
                        $filesDeleted++;
                    }

                    $export->update([
                        'status' => ExportRequestStatus::Expired,
                        'storage_path' => null,
                    ]);

                    $expired++;
                }
            });

        return new ExportCleanupResult(
            expired: $expired,
            filesDeleted: $filesDeleted,
        );
    }

    public function deleteStoredFile(ExportRequest $export, ?string $disk = null): bool
    {
        if ($export->storage_path === null || $export->storage_path === '') {
            return false;
        }

        $disk ??= (string) config('exports.disk', 'local');

        if (! Storage::disk($disk)->exists($export->storage_path)) {
            return false;
        }

        Storage::disk($disk)->delete($export->storage_path);

        return true;
    }
}
