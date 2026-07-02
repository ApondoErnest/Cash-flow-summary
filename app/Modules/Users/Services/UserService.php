<?php

declare(strict_types=1);

namespace App\Modules\Users\Services;

use App\Models\User;
use App\Modules\Authentication\Services\PasswordService;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\CenterService;
use App\Support\Auth\RoleName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class UserService
{
    /**
     * @param  array{
     *     search?: string,
     *     center_id?: int|string|null,
     *     role?: string|null,
     *     status?: string|null,
     * }  $filters
     * @return Collection<int, User>
     */
    public function listForOrganization(User $owner, array $filters = []): Collection
    {
        $query = $this->baseQuery($owner)
            ->with(['center', 'roles'])
            ->orderBy('name');

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $query->where(function (Builder $builder) use ($needle): void {
                $builder
                    ->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(username) LIKE ?', ["%{$needle}%"]);
            });
        }

        if (($filters['center_id'] ?? null) !== null && $filters['center_id'] !== '') {
            $query->where('center_id', (int) $filters['center_id']);
        }

        $role = (string) ($filters['role'] ?? '');

        if ($role !== '') {
            $query->whereHas('roles', fn (Builder $builder) => $builder->where('name', $role));
        }

        $status = (string) ($filters['status'] ?? 'all');

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{user: User, temporary_password: string}
     */
    public function create(User $owner, array $data): array
    {
        $this->assertStaffRole((string) $data['role']);
        $centerId = $this->resolveCenterId($owner, (string) $data['role'], $data['center_id'] ?? null);
        $this->assertUniqueUsername((string) $data['username']);

        $temporaryPassword = Str::password(16, symbols: true, numbers: true);

        $user = User::query()->create([
            'organization_id' => $owner->organization_id,
            'center_id' => $centerId,
            'name' => $data['name'],
            'username' => $data['username'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'password' => $temporaryPassword,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $this->syncRole($user, (string) $data['role']);

        return [
            'user' => $user->fresh(['center', 'roles']),
            'temporary_password' => $temporaryPassword,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $owner, User $user, array $data): User
    {
        $this->assertBelongsToOrganization($owner, $user);

        if ($user->isOwner()) {
            if ((int) $owner->id === (int) $user->id && ($data['is_active'] ?? true) === false) {
                throw ValidationException::withMessages([
                    'is_active' => __('user.manage.validation.cannot_deactivate_self'),
                ]);
            }

            $user->fill([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
            ])->save();

            return $user->fresh(['center', 'roles']);
        }

        $role = (string) $data['role'];
        $this->assertStaffRole($role);
        $this->assertUniqueUsername((string) $data['username'], $user->id);
        $centerId = $this->resolveCenterId($owner, $role, $data['center_id'] ?? null);

        $user->fill([
            'name' => $data['name'],
            'username' => $data['username'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'center_id' => $centerId,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();

        $this->syncRole($user, $role);

        return $user->fresh(['center', 'roles']);
    }

    public function resetPassword(User $owner, User $user, PasswordService $passwordService): string
    {
        $this->assertBelongsToOrganization($owner, $user);

        if ((int) $owner->id === (int) $user->id) {
            throw ValidationException::withMessages([
                'user' => __('user.manage.validation.cannot_reset_self'),
            ]);
        }

        return $passwordService->assignTemporaryPassword($user);
    }

    public function roleLabel(User $user): string
    {
        $roleName = $user->roles->first()?->name;

        return match ($roleName) {
            RoleName::Owner => __('roles.owner'),
            RoleName::CenterManager => __('roles.manager'),
            RoleName::Cashier => __('roles.cashier'),
            default => __('user.manage.role_unknown'),
        };
    }

    /**
     * @return list<string>
     */
    public function assignableRoles(): array
    {
        return [
            RoleName::CenterManager,
            RoleName::Cashier,
        ];
    }

    private function baseQuery(User $owner): Builder
    {
        return User::query()->where('organization_id', $owner->organization_id);
    }

    private function assertBelongsToOrganization(User $owner, User $user): void
    {
        if ((int) $user->organization_id !== (int) $owner->organization_id) {
            throw ValidationException::withMessages([
                'user' => __('user.manage.validation.invalid_user'),
            ]);
        }
    }

    private function assertStaffRole(string $role): void
    {
        if (! in_array($role, $this->assignableRoles(), true)) {
            throw ValidationException::withMessages([
                'role' => __('user.manage.validation.invalid_role'),
            ]);
        }
    }

    private function resolveCenterId(User $owner, string $role, mixed $centerId): int
    {
        if ($role === RoleName::Owner) {
            throw ValidationException::withMessages([
                'role' => __('user.manage.validation.invalid_role'),
            ]);
        }

        if ($centerId === null || $centerId === '') {
            throw ValidationException::withMessages([
                'center_id' => __('user.manage.validation.center_required'),
            ]);
        }

        $center = Center::query()->find((int) $centerId);

        if ($center === null || ! app(CenterService::class)->belongsToOrganization($center, $owner)) {
            throw ValidationException::withMessages([
                'center_id' => __('user.manage.validation.invalid_center'),
            ]);
        }

        if (! $center->is_active) {
            throw ValidationException::withMessages([
                'center_id' => __('user.manage.validation.inactive_center'),
            ]);
        }

        return (int) $center->id;
    }

    private function assertUniqueUsername(string $username, ?int $ignoreUserId = null): void
    {
        $exists = User::query()
            ->where('username', $username)
            ->when($ignoreUserId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreUserId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'username' => __('user.manage.validation.username_taken'),
            ]);
        }
    }

    private function syncRole(User $user, string $role): void
    {
        Role::findOrCreate($role, 'web');
        $user->syncRoles([$role]);
    }
}
