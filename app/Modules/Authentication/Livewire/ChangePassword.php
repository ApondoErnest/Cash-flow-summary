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
class ChangePassword extends Component
{
    public string $currentPassword = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(PasswordService $passwordService): void
    {
        $user = auth()->user();

        if ($user === null) {
            $this->redirect(route('login'), navigate: true);
        }

        if ($user && ! $passwordService->mustChange($user)) {
            $this->redirect(app(AuthenticationRedirectService::class)->nextRoute($user), navigate: true);
        }
    }

    public function updatePassword(
        PasswordService $passwordService,
        TwoFactorService $twoFactorService,
        AuthenticationRedirectService $redirectService,
    ): void {
        $this->validate(
            [
                'currentPassword' => ['required', 'string'],
                'password' => ['required', 'string', 'confirmed'],
                'password_confirmation' => ['required', 'string'],
            ],
            [],
            [
                'currentPassword' => __('password.current_password'),
                'password' => __('password.new_password'),
                'password_confirmation' => __('password.confirm_password'),
            ],
        );

        $user = auth()->user();

        if ($user === null) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        try {
            $passwordService->change($user, $this->currentPassword, $this->password);
        } catch (ValidationException $exception) {
            $this->reset('currentPassword', 'password', 'password_confirmation');

            throw $exception;
        }

        $this->reset('currentPassword', 'password', 'password_confirmation');

        $twoFactorService->clearVerification();

        $this->redirect($redirectService->nextRoute($user->fresh()), navigate: true);
    }

    public function render()
    {
        return view('livewire.authentication.change-password')
            ->title(__('password.change_title'));
    }
}
