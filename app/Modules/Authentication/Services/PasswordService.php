<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Services;

use App\Models\User;
use App\Support\Auth\PasswordRules;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PasswordService
{
    public function change(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => [__('password.current_invalid')],
            ]);
        }

        if (Hash::check($newPassword, $user->password)) {
            throw ValidationException::withMessages([
                'password' => [__('password.same_as_current')],
            ]);
        }

        $validator = validator(
            ['password' => $newPassword],
            ['password' => PasswordRules::defaults(confirmed: false)],
            [],
            ['password' => __('password.new_password')],
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $user->forceFill([
            'password' => $newPassword,
            'must_change_password' => false,
        ])->save();

        if (! Hash::check($newPassword, $user->fresh()->password)) {
            throw ValidationException::withMessages([
                'password' => [__('password.update_failed')],
            ]);
        }
    }

    public function mustChange(User $user): bool
    {
        return (bool) $user->must_change_password;
    }

    public function assignTemporaryPassword(User $user): string
    {
        $plainPassword = Str::password(16, symbols: true, numbers: true);

        $user->forceFill([
            'password' => $plainPassword,
            'must_change_password' => true,
        ])->save();

        return $plainPassword;
    }
}
