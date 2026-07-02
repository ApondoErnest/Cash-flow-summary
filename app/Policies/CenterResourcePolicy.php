<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksCenterScope;
use App\Support\Center\CenterContextResolver;
use Illuminate\Database\Eloquent\Model;

abstract class CenterResourcePolicy
{
    use ChecksCenterScope;

    public function view(User $user, Model $resource): bool
    {
        return $this->resourceBelongsToResolvedCenter($user, $resource);
    }

    public function import(User $user): bool
    {
        return app(CenterContextResolver::class)->canImport($user);
    }

    public function download(User $user, Model $resource): bool
    {
        return $this->view($user, $resource);
    }
}
