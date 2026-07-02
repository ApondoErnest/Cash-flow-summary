<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Support;

final class VerificationCleanupResult
{
    public function __construct(
        public readonly int $expired,
        public readonly int $filesDeleted,
        public readonly int $orphanFilesDeleted,
    ) {}

    /**
     * @return array{expired: int, files_deleted: int, orphan_files_deleted: int}
     */
    public function toArray(): array
    {
        return [
            'expired' => $this->expired,
            'files_deleted' => $this->filesDeleted,
            'orphan_files_deleted' => $this->orphanFilesDeleted,
        ];
    }
}
