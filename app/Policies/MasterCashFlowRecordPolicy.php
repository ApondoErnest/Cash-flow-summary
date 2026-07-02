<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Support\Center\CenterContextResolver;

class MasterCashFlowRecordPolicy extends CenterResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return app(CenterContextResolver::class)->canImport($user);
    }

    public function view(User $user, MasterCashFlowRecord $record): bool
    {
        return $this->resourceBelongsToResolvedCenter($user, $record);
    }
}
