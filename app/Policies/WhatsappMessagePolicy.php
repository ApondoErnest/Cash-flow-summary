<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Support\Center\CenterContextResolver;

class WhatsappMessagePolicy extends CenterResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, WhatsappMessage $message): bool
    {
        if (! $user->isOwner()) {
            return false;
        }

        if ($message->center_id === null) {
            return false;
        }

        return $this->resourceBelongsToResolvedCenter($user, $message);
    }
}
