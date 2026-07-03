<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\Reports\Models\ExportRequest;
use Illuminate\Database\Eloquent\Model;

class ReportExportPolicy extends CenterResourcePolicy
{
    public function view(User $user, Model $resource): bool
    {
        if (! $resource instanceof ExportRequest) {
            return false;
        }

        if (! $this->resourceBelongsToResolvedCenter($user, $resource)) {
            return false;
        }

        if ($user->isOwner()) {
            return true;
        }

        return (int) $resource->user_id === (int) $user->id;
    }
}
