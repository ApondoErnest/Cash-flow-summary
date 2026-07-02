<?php

declare(strict_types=1);

namespace App\Modules\AuditLogging\Services;

use App\Models\User;
use App\Modules\AuditLogging\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class AuditLogService
{
    /**
     * @param  array{
     *     search?: string,
     *     center_id?: int|null,
     *     event?: string|null,
     *     from?: string|null,
     *     to?: string|null,
     * }  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function listForOrganization(User $owner, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $organizationId = (int) $owner->organization_id;

        $query = AuditLog::query()
            ->withoutCenterScope()
            ->with([
                'user:id,name,username,organization_id',
                'center:id,name,code,organization_id',
            ])
            ->where(function ($builder) use ($organizationId): void {
                $builder
                    ->whereHas('center', fn ($center) => $center->where('organization_id', $organizationId))
                    ->orWhere(function ($builder) use ($organizationId): void {
                        $builder
                            ->whereNull('center_id')
                            ->whereHas('user', fn ($user) => $user->where('organization_id', $organizationId));
                    });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (! empty($filters['center_id'])) {
            $query->where('center_id', (int) $filters['center_id']);
        }

        if (! empty($filters['event'])) {
            $query->where('event', (string) $filters['event']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];

            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('event', 'like', '%'.$search.'%')
                    ->orWhere('resource_type', 'like', '%'.$search.'%')
                    ->orWhere('reason', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function ($user) use ($search): void {
                        $user
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('username', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('center', function ($center) use ($search): void {
                        $center
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('code', 'like', '%'.$search.'%');
                    });
            });
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', (string) $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', (string) $filters['to']);
        }

        return $query->paginate($perPage);
    }

    /**
     * @return Collection<int, string>
     */
    public function distinctEventsForOrganization(User $owner): Collection
    {
        $organizationId = (int) $owner->organization_id;

        return AuditLog::query()
            ->withoutCenterScope()
            ->where(function ($builder) use ($organizationId): void {
                $builder
                    ->whereHas('center', fn ($center) => $center->where('organization_id', $organizationId))
                    ->orWhere(function ($builder) use ($organizationId): void {
                        $builder
                            ->whereNull('center_id')
                            ->whereHas('user', fn ($user) => $user->where('organization_id', $organizationId));
                    });
            })
            ->distinct()
            ->orderBy('event')
            ->pluck('event');
    }

    public function eventLabel(string $event): string
    {
        $key = 'audit.events.'.str_replace('.', '_', $event);
        $label = __($key);

        return $label !== $key ? $label : $event;
    }

    public function resourceLabel(?string $resourceType, ?int $resourceId): ?string
    {
        if ($resourceType === null) {
            return null;
        }

        $shortType = class_basename($resourceType);

        if ($resourceId === null) {
            return $shortType;
        }

        return $shortType.' #'.$resourceId;
    }
}
