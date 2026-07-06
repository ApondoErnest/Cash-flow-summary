<?php

declare(strict_types=1);

namespace App\Support\Security;

use Illuminate\Support\Facades\URL;

final class ProductionSecurityBootstrap
{
    public static function apply(): void
    {
        if (! config('production_security.force_https')) {
            return;
        }

        URL::forceScheme('https');

        if (config('session.secure') === null) {
            config(['session.secure' => true]);
        }
    }
}
