<?php

declare(strict_types=1);

namespace App\Support\Center;

final readonly class ActiveCenterContext
{
    public function __construct(
        public int $centerId,
        public int $organizationId,
        public string $centerName,
        public int $ownerUserId,
    ) {}
}
