<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Reports\Services\ExportCleanupService;
use Illuminate\Console\Command;

final class CleanupExportsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'exports:cleanup';

    /**
     * @var string
     */
    protected $description = 'Expire completed report exports and delete stored files';

    public function handle(ExportCleanupService $cleanupService): int
    {
        $result = $cleanupService->run();

        $this->components->info(sprintf(
            'Expired %d export(s); deleted %d stored file(s).',
            $result->expired,
            $result->filesDeleted,
        ));

        return self::SUCCESS;
    }
}
