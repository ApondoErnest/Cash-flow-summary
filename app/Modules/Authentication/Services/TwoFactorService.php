<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class TwoFactorService
{
    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    public function mustVerify(User $user): bool
    {
        return $user->isOwner() && $user->hasTwoFactorEnabled();
    }

    public function isVerified(): bool
    {
        return (bool) Session::get($this->verifiedSessionKey(), false);
    }

    public function markVerified(): void
    {
        Session::put($this->verifiedSessionKey(), true);
    }

    public function clearVerification(): void
    {
        Session::forget($this->verifiedSessionKey());
    }

    public function generateSecretKey(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function otpAuthUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            (string) config('auth_security.two_factor.issuer'),
            $user->username,
            $secret,
        );
    }

    public function qrCodeSvg(string $otpAuthUrl, int $size = 192): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($otpAuthUrl);
    }

    public function verify(User $user, string $code): bool
    {
        $secret = $this->decryptSecret($user->two_factor_secret);

        if ($secret === null) {
            return false;
        }

        return $this->verifyWithSecret($secret, $code);
    }

    public function verifyWithSecret(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, preg_replace('/\s+/', '', $code) ?? '');
    }

    /**
     * @return list<string> Plaintext recovery codes (shown once to the user)
     */
    public function enable(User $user, string $secret): array
    {
        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $this->encryptSecret($secret),
            'two_factor_recovery_codes' => $this->encryptRecoveryCodes($recoveryCodes),
        ])->save();

        return $recoveryCodes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $this->clearVerification();
    }

    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $this->decryptRecoveryCodes($user->two_factor_recovery_codes);

        if ($codes === []) {
            return false;
        }

        $normalized = Str::upper(str_replace(' ', '', trim($code)));
        $index = array_search($normalized, array_map(
            fn (string $stored) => Str::upper(str_replace(' ', '', $stored)),
            $codes,
        ), true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);

        $user->forceFill([
            'two_factor_recovery_codes' => $this->encryptRecoveryCodes(array_values($codes)),
        ])->save();

        return true;
    }

    /**
     * @return list<string>
     */
    private function generateRecoveryCodes(): array
    {
        $count = (int) config('auth_security.two_factor.recovery_codes_count', 8);

        return collect(range(1, $count))
            ->map(fn () => Str::upper(Str::random(4).'-'.Str::random(4)))
            ->all();
    }

    private function verifiedSessionKey(): string
    {
        return (string) config('auth_security.two_factor.verified_session_key', 'auth.two_factor.verified');
    }

    private function encryptSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    private function decryptSecret(?string $encrypted): ?string
    {
        if (! filled($encrypted)) {
            return null;
        }

        return Crypt::decryptString($encrypted);
    }

    /**
     * @param  list<string>  $codes
     */
    private function encryptRecoveryCodes(array $codes): string
    {
        return Crypt::encryptString(json_encode($codes, JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<string>
     */
    private function decryptRecoveryCodes(?string $encrypted): array
    {
        if (! filled($encrypted)) {
            return [];
        }

        $decoded = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? array_values(array_map(strval(...), $decoded)) : [];
    }
}
