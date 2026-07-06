<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Support\Auth\RoleName;
use Illuminate\Database\Eloquent\Model;

class ImportVerificationPolicy extends CenterResourcePolicy
{
    public function download(User $user, Model $resource): bool
    {
        if (! $resource instanceof ImportVerification) {
            return false;
        }

        if (! $this->resourceBelongsToResolvedCenter($user, $resource)) {
            return false;
        }

        if ($user->isOwner() || $user->hasRole(RoleName::CenterManager)) {
            return true;
        }

        return (int) $resource->user_id === (int) $user->id;
    }
}
