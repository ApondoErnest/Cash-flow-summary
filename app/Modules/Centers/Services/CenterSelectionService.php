<?php

declare(strict_types=1);

namespace App\Modules\Centers\Services;

use App\Models\User;
use App\Modules\Centers\Models\Center;
use Illuminate\Support\Collection;

final class CenterSelectionService
{
    /**
     * @return Collection<int, Center>
     */
    public function activeCentersFor(User $user): Collection
    {
        return Center::query()
            ->where('organization_id', $user->organization_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function displayLabel(Center $center): string
    {
        $suffix = trim((string) ($center->code ?: $center->city ?: ''));

        if ($suffix === '') {
            return $center->name;
        }

        return "{$center->name} — {$suffix}";
    }

    /**
     * @return Collection<int, Center>
     */
    public function searchCenters(Collection $centers, string $query): Collection
    {
        $query = trim(mb_strtolower($query));

        if ($query === '') {
            return $centers;
        }

        return $centers->filter(function (Center $center) use ($query): bool {
            $haystack = mb_strtolower(implode(' ', array_filter([
                $center->name,
                $center->code,
                $center->city,
                $center->region,
            ])));

            return str_contains($haystack, $query);
        })->values();
    }

    public function findSelectableCenter(User $user, int $centerId): ?Center
    {
        return $this->activeCentersFor($user)->firstWhere('id', $centerId);
    }
}
