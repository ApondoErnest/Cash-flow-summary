<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\Centers\Models\Center;

class CenterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isCenterStaff();
    }

    public function view(User $user, Center $center): bool
    {
        if ((int) $center->organization_id !== (int) $user->organization_id) {
            return false;
        }

        if ($user->isOwner()) {
            return true;
        }

        return (int) $user->center_id === (int) $center->id;
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Center $center): bool
    {
        return $user->isOwner()
            && (int) $center->organization_id === (int) $user->organization_id;
    }

    public function delete(User $user, Center $center): bool
    {
        return $this->update($user, $center);
    }
}
