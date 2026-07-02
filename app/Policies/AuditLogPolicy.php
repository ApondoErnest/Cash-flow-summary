<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\AuditLogging\Models\AuditLog;
use App\Policies\Concerns\ChecksCenterScope;

class AuditLogPolicy
{
    use ChecksCenterScope;

    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        if (! $user->isOwner()) {
            return false;
        }

        if ($this->isOwnerAdminContext($user)) {
            return true;
        }

        if ($auditLog->center_id === null) {
            return false;
        }

        return $this->resourceBelongsToResolvedCenter($user, $auditLog);
    }
}
