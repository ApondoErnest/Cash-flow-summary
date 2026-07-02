<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Services;

use App\Modules\CsvVerification\Models\ImportVerification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class FileStorageService
{
    public function promoteVerificationFile(ImportVerification $verification, int $importId): string
    {
        $tempDisk = (string) config('csv_verification.temp_disk', 'local');
        $permanentDisk = (string) config('csv_imports.permanent_disk', 'local');
        $directory = trim((string) config('csv_imports.permanent_directory', 'imports'), '/');
        $relativePath = "{$directory}/{$verification->center_id}/{$importId}/{$verification->token}.csv";

        if (! Storage::disk($tempDisk)->exists($verification->temp_storage_path)) {
            throw new RuntimeException('Verification temp file is missing.');
        }

        $contents = Storage::disk($tempDisk)->get($verification->temp_storage_path);

        if ($contents === null) {
            throw new RuntimeException('Verification temp file could not be read.');
        }

        Storage::disk($permanentDisk)->put($relativePath, $contents);

        return $relativePath;
    }

    public function absolutePath(string $relativePath): string
    {
        $disk = (string) config('csv_imports.permanent_disk', 'local');

        return Storage::disk($disk)->path($relativePath);
    }
}
