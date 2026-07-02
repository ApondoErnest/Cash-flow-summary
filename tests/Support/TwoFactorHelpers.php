<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Authentication\Services\TwoFactorService;
use PragmaRX\Google2FA\Google2FA;

function enableTwoFactorFor(User $user, ?string $secret = null): string
{
    $google2fa = app(Google2FA::class);
    $secret ??= $google2fa->generateSecretKey();

    app(TwoFactorService::class)->enable($user, $secret);

    return $secret;
}

function currentTwoFactorCode(string $secret): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}
