<?php

declare(strict_types=1);

namespace App\Support\Center;

final readonly class ResolvedCenterContext
{
    public const SOURCE_ACTIVE = 'active';

    public const SOURCE_ASSIGNED = 'assigned';

    public const SOURCE_JOB = 'job';

    public function __construct(
        public int $centerId,
        public int $organizationId,
        public string $centerName,
        public string $source,
    ) {}

    public static function fromActiveCenter(ActiveCenterContext $context): self
    {
        return new self(
            centerId: $context->centerId,
            organizationId: $context->organizationId,
            centerName: $context->centerName,
            source: self::SOURCE_ACTIVE,
        );
    }

    public static function fromAssignedCenter(AssignedCenterContext $context): self
    {
        return new self(
            centerId: $context->centerId,
            organizationId: $context->organizationId,
            centerName: $context->centerName,
            source: self::SOURCE_ASSIGNED,
        );
    }
}
