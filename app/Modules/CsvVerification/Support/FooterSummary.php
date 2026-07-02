<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Support;

final class FooterSummary
{
    public function __construct(
        public readonly int $count,
        public readonly int $ht,
        public readonly int $vat,
        public readonly int $ttc,
    ) {}

    /**
     * @return array{count: int, ht: int, vat: int, ttc: int}
     */
    public function toArray(): array
    {
        return [
            'count' => $this->count,
            'ht' => $this->ht,
            'vat' => $this->vat,
            'ttc' => $this->ttc,
        ];
    }
}
