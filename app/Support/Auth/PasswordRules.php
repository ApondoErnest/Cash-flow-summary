<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Validation\Rules\Password;

final class PasswordRules
{
    public static function rule(): Password
    {
        $rule = Password::min((int) config('auth_security.password.min_length', 12));

        if (config('auth_security.password.require_mixed_case', true)) {
            $rule->mixedCase();
        }

        if (config('auth_security.password.require_numbers', true)) {
            $rule->numbers();
        }

        if (config('auth_security.password.require_symbols', true)) {
            $rule->symbols();
        }

        return $rule;
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    public static function defaults(bool $confirmed = true): array
    {
        $rules = ['required', 'string', self::rule()];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }
}
