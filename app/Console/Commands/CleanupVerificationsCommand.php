<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\CsvVerification\Services\VerificationCleanupService;
use Illuminate\Console\Command;

final class CleanupVerificationsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'csv-verification:cleanup';

    /**
     * @var string
     */
    protected $description = 'Expire abandoned import verifications and delete temporary CSV files';

    public function handle(VerificationCleanupService $cleanupService): int
    {
        $result = $cleanupService->run();

        $this->components->info(sprintf(
            'Expired %d verification(s); deleted %d temp file(s); removed %d orphaned rejected file(s).',
            $result->expired,
            $result->filesDeleted,
            $result->orphanFilesDeleted,
        ));

        return self::SUCCESS;
    }
}
