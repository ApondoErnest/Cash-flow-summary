<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, User $model): bool
    {
        return $this->manageOrganizationUser($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, User $model): bool
    {
        return $this->manageOrganizationUser($user, $model);
    }

    public function resetPassword(User $user, User $model): bool
    {
        return $this->manageOrganizationUser($user, $model)
            && (int) $user->id !== (int) $model->id;
    }

    private function manageOrganizationUser(User $user, User $model): bool
    {
        return $user->isOwner()
            && (int) $model->organization_id === (int) $user->organization_id;
    }
}
