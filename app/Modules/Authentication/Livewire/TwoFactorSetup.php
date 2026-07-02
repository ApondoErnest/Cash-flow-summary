<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Livewire;

use App\Modules\Authentication\Services\PasswordService;
use App\Modules\Authentication\Services\TwoFactorService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class TwoFactorSetup extends Component
{
    public string $pendingSecret = '';

    public string $confirmationCode = '';

    /** @var list<string> */
    public array $recoveryCodes = [];

    public bool $showRecoveryCodes = false;

    public function mount(TwoFactorService $twoFactorService, PasswordService $passwordService): void
    {
        $user = auth()->user();

        if ($user === null || ! $user->isOwner()) {
            abort(403);
        }

        if ($passwordService->mustChange($user)) {
            $this->redirect(route('password.change'), navigate: true);
        }

        if ($twoFactorService->mustVerify($user) && ! $twoFactorService->isVerified()) {
            $this->redirect(route('two-factor.challenge'), navigate: true);
        }

        if (! $user->hasTwoFactorEnabled()) {
            $this->pendingSecret = $twoFactorService->generateSecretKey();
        }
    }

    public function confirm(TwoFactorService $twoFactorService): void
    {
        $user = auth()->user();

        if ($user === null || ! $user->isOwner() || $user->hasTwoFactorEnabled()) {
            abort(403);
        }

        $this->validate(
            ['confirmationCode' => ['required', 'string']],
            [],
            ['confirmationCode' => __('two_factor.code')],
        );

        if (! $twoFactorService->verifyWithSecret($this->pendingSecret, $this->confirmationCode)) {
            $this->reset('confirmationCode');

            throw ValidationException::withMessages([
                'confirmationCode' => [__('two_factor.invalid_code')],
            ]);
        }

        $this->recoveryCodes = $twoFactorService->enable($user, $this->pendingSecret);
        $this->showRecoveryCodes = true;
        $twoFactorService->markVerified();

        $this->reset('confirmationCode', 'pendingSecret');
    }

    public function disable(TwoFactorService $twoFactorService): void
    {
        $user = auth()->user();

        if ($user === null || ! $user->isOwner()) {
            abort(403);
        }

        $twoFactorService->disable($user->fresh());

        $this->reset('recoveryCodes', 'showRecoveryCodes');
        $this->pendingSecret = $twoFactorService->generateSecretKey();
    }

    public function render(TwoFactorService $twoFactorService)
    {
        $user = auth()->user();

        return view('livewire.authentication.two-factor-setup', [
            'user' => $user,
            'qrCode' => $user && ! $user->hasTwoFactorEnabled() && filled($this->pendingSecret)
                ? $twoFactorService->qrCodeSvg($twoFactorService->otpAuthUrl($user, $this->pendingSecret))
                : null,
            'otpAuthUrl' => $user && ! $user->hasTwoFactorEnabled() && filled($this->pendingSecret)
                ? $twoFactorService->otpAuthUrl($user, $this->pendingSecret)
                : null,
        ])->title(__('two_factor.setup_title'));
    }
}
