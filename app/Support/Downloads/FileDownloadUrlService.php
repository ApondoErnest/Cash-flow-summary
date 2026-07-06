<?php

declare(strict_types=1);

namespace App\Support\Downloads;

use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\Reports\Models\ExportRequest;
use Illuminate\Support\Facades\URL;

final class FileDownloadUrlService
{
    public function verificationErrors(ImportVerification $verification): string
    {
        return $this->signed('verifications.errors.download', [
            'token' => $verification->token,
        ]);
    }

    public function importErrors(Import $import): string
    {
        return $this->signed('imports.errors.download', [
            'import' => $import->id,
        ]);
    }

    public function export(ExportRequest $export): string
    {
        return $this->signed('exports.download', [
            'exportRequest' => $export->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function signed(string $routeName, array $parameters): string
    {
        return URL::temporarySignedRoute(
            $routeName,
            now()->addMinutes($this->ttlMinutes()),
            $parameters,
        );
    }

    private function ttlMinutes(): int
    {
        return max(1, (int) config('downloads.signed_url_ttl_minutes', 30));
    }
}
