<?php

declare(strict_types=1);

namespace App\Support\Security;

final class ContentSecurityPolicyBuilder
{
    /**
     * @param  array<string, string>  $directives
     */
    public function build(array $directives): string
    {
        $parts = [];

        foreach ($directives as $name => $value) {
            if ($value === '') {
                continue;
            }

            $parts[] = trim($name).' '.trim($value);
        }

        return implode('; ', $parts);
    }
}
