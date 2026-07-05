<?php

declare(strict_types=1);

namespace App\Modules\Centers\Services;

use App\Models\User;
use App\Modules\AuditLogging\Services\AuditLogger;
use App\Modules\Centers\Models\Center;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class CenterService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return Collection<int, Center>
     */
    public function listForOrganization(User $owner): Collection
    {
        return $this->baseQuery($owner)
            ->withCount([
                'assignedUsers as active_users_count' => fn (Builder $query) => $query->where('is_active', true),
            ])
            ->orderBy('name')
            ->get();
    }

    public function locationLabel(Center $center): string
    {
        return collect([$center->city, $center->region])
            ->filter(fn (?string $value) => filled($value))
            ->implode(', ');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $owner, array $data): Center
    {
        $this->assertUniqueCode($owner, (string) $data['code']);

        $center = Center::query()->create([
            'organization_id' => $owner->organization_id,
            'name' => $data['name'],
            'code' => $data['code'],
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'region' => $data['region'] ?? null,
            'phone' => $data['phone'] ?? null,
            'default_language' => $data['default_language'] ?? 'fr',
            'submission_deadline' => $data['submission_deadline'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->auditLogger->record(
            event: 'center.created',
            user: $owner,
            centerId: (int) $center->id,
            resourceType: Center::class,
            resourceId: (int) $center->id,
            newValues: [
                'name' => $data['name'],
                'code' => $data['code'],
                'is_active' => $data['is_active'] ?? true,
            ],
        );

        return $center;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Center $center, User $owner, array $data): Center
    {
        $this->assertBelongsToOrganization($center, $owner);
        $this->assertUniqueCode($owner, (string) $data['code'], $center->id);

        $previousValues = [
            'name' => $center->name,
            'code' => $center->code,
            'is_active' => $center->is_active,
        ];

        $center->fill([
            'name' => $data['name'],
            'code' => $data['code'],
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'region' => $data['region'] ?? null,
            'phone' => $data['phone'] ?? null,
            'default_language' => $data['default_language'] ?? 'fr',
            'submission_deadline' => $data['submission_deadline'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ])->save();

        $this->auditLogger->record(
            event: 'center.updated',
            user: $owner,
            centerId: (int) $center->id,
            resourceType: Center::class,
            resourceId: (int) $center->id,
            oldValues: $previousValues,
            newValues: [
                'name' => $data['name'],
                'code' => $data['code'],
                'is_active' => $data['is_active'] ?? true,
            ],
        );

        return $center->fresh();
    }

    public function belongsToOrganization(Center $center, User $owner): bool
    {
        return (int) $center->organization_id === (int) $owner->organization_id;
    }

    private function baseQuery(User $owner): Builder
    {
        return Center::query()->where('organization_id', $owner->organization_id);
    }

    public function assertBelongsToOrganization(Center $center, User $owner): void
    {
        if (! $this->belongsToOrganization($center, $owner)) {
            throw ValidationException::withMessages([
                'center' => __('center.manage.invalid'),
            ]);
        }
    }

    private function assertUniqueCode(User $owner, string $code, ?int $ignoreCenterId = null): void
    {
        $exists = Center::query()
            ->where('organization_id', $owner->organization_id)
            ->where('code', $code)
            ->when($ignoreCenterId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreCenterId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => __('center.manage.code_taken'),
            ]);
        }
    }
}
