<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\CsvImports\Models\Import;

class ImportPolicy extends CenterResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return app(\App\Support\Center\CenterContextResolver::class)->canImport($user);
    }

    public function view(User $user, Import $import): bool
    {
        return $this->resourceBelongsToResolvedCenter($user, $import);
    }
}
