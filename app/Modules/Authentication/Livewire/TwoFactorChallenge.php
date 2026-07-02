<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Livewire;

use App\Modules\Authentication\Services\AuthenticationRedirectService;
use App\Modules\Authentication\Services\PasswordService;
use App\Modules\Authentication\Services\TwoFactorService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class TwoFactorChallenge extends Component
{
    public string $code = '';

    public bool $useRecoveryCode = false;

    public function mount(TwoFactorService $twoFactorService, PasswordService $passwordService): void
    {
        $user = auth()->user();

        if ($user === null) {
            $this->redirect(route('login'), navigate: true);
        }

        if ($user && $passwordService->mustChange($user)) {
            $this->redirect(route('password.change'), navigate: true);
        }

        if ($user === null || ! $twoFactorService->mustVerify($user)) {
            $this->redirect(app(AuthenticationRedirectService::class)->nextRoute($user), navigate: true);
        }

        if ($twoFactorService->isVerified()) {
            $this->redirectIntended(
                default: app(AuthenticationRedirectService::class)->nextRoute($user),
                navigate: true,
            );
        }
    }

    public function verify(
        TwoFactorService $twoFactorService,
        AuthenticationRedirectService $redirectService,
    ): void
    {
        $this->validate(
            ['code' => ['required', 'string']],
            [],
            ['code' => $this->useRecoveryCode ? __('two_factor.recovery_code') : __('two_factor.code')],
        );

        $user = auth()->user();

        if ($user === null) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $verified = $this->useRecoveryCode
            ? $twoFactorService->consumeRecoveryCode($user, $this->code)
            : $twoFactorService->verify($user, $this->code);

        if (! $verified) {
            $this->reset('code');

            throw ValidationException::withMessages([
                'code' => [$this->useRecoveryCode
                    ? __('two_factor.invalid_recovery_code')
                    : __('two_factor.invalid_code')],
            ]);
        }

        $twoFactorService->markVerified();

        $this->redirect($redirectService->nextRoute($user->fresh()), navigate: true);
    }

    public function render()
    {
        return view('livewire.authentication.two-factor-challenge')
            ->title(__('two_factor.challenge_title'));
    }
}
