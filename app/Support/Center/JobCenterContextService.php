<?php

declare(strict_types=1);

namespace App\Support\Center;

use App\Modules\Centers\Models\Center;
use InvalidArgumentException;

final class JobCenterContextService
{
    private ?ResolvedCenterContext $context = null;

    public function isBound(): bool
    {
        return $this->context !== null;
    }

    public function bind(int $centerId): void
    {
        $center = Center::query()->find($centerId);

        if ($center === null || ! $center->is_active) {
            throw new InvalidArgumentException(__('center.active_center_invalid'));
        }

        $this->context = new ResolvedCenterContext(
            centerId: (int) $center->id,
            organizationId: (int) $center->organization_id,
            centerName: $center->name,
            source: ResolvedCenterContext::SOURCE_JOB,
        );
    }

    public function release(): void
    {
        $this->context = null;
    }

    public function resolve(): ?ResolvedCenterContext
    {
        return $this->context;
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runForCenter(int $centerId, callable $callback): mixed
    {
        $this->bind($centerId);

        try {
            return $callback();
        } finally {
            $this->release();
        }
    }
}
