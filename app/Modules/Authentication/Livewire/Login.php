<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Livewire;

use App\Modules\Authentication\Services\AuthenticationRedirectService;
use App\Modules\Authentication\Services\LoginService;
use App\Modules\Authentication\Services\TwoFactorService;
use App\Support\Locale\AppLocale;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class Login extends Component
{
    public string $username = '';

    public string $password = '';

    public bool $remember = false;

    public string $locale = '';

    public function mount(): void
    {
        $this->locale = AppLocale::resolve();
    }

    public function updatedLocale(string $locale): void
    {
        if (AppLocale::isSupported($locale)) {
            AppLocale::set($locale);
        }
    }

    public function authenticate(
        LoginService $loginService,
        TwoFactorService $twoFactorService,
        AuthenticationRedirectService $redirectService,
    ): void {
        $this->validate(
            [
                'username' => ['required', 'string'],
                'password' => ['required', 'string'],
                'locale' => ['required', 'in:'.implode(',', AppLocale::supported())],
            ],
            [],
            [
                'username' => __('auth.username'),
                'password' => __('auth.password'),
            ],
        );

        AppLocale::set($this->locale);

        try {
            $loginService->authenticate(
                username: $this->username,
                password: $this->password,
                remember: $this->remember,
                ipAddress: (string) request()->ip(),
            );
        } catch (ValidationException $exception) {
            $this->reset('password');

            throw $exception;
        }

        $this->reset('password');

        session()->regenerate();

        $user = auth()->user();

        if ($user === null) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $twoFactorService->clearVerification();

        $this->redirect($redirectService->nextRoute($user), navigate: true);
    }

    public function render()
    {
        return view('livewire.authentication.login', [
            'authStatus' => session('auth_status'),
        ])->title(__('auth.sign_in'));
    }
}
