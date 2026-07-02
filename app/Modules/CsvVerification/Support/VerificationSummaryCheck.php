<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Support;

final readonly class VerificationSummaryCheck
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $passed,
    ) {}
}
