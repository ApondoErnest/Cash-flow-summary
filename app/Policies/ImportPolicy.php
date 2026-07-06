<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\CsvImports\Models\Import;
use Illuminate\Database\Eloquent\Model;

class ImportPolicy extends CenterResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return app(\App\Support\Center\CenterContextResolver::class)->canImport($user);
    }

    public function view(User $user, Model $resource): bool
    {
        if (! $resource instanceof Import) {
            return false;
        }

        return $this->resourceBelongsToResolvedCenter($user, $resource);
    }
}
