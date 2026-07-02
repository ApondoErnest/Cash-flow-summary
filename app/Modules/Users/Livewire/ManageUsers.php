<?php

declare(strict_types=1);

namespace App\Modules\Users\Livewire;

use App\Models\User;
use App\Modules\Authentication\Services\PasswordService;
use App\Modules\Centers\Models\Center;
use App\Modules\Users\Services\UserService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ManageUsers extends Component
{
    use AuthorizesRequests;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'center', history: true)]
    public string $centerFilter = '';

    #[Url(as: 'role', history: true)]
    public string $roleFilter = '';

    #[Url(as: 'status', history: true)]
    public string $statusFilter = 'all';

    public ?string $temporaryPassword = null;

    public ?string $temporaryPasswordUsername = null;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function resetPassword(
        int $userId,
        UserService $userService,
        PasswordService $passwordService,
    ): void {
        $user = User::query()->findOrFail($userId);
        $this->authorize('resetPassword', $user);

        $owner = auth()->user();

        if ($owner === null) {
            return;
        }

        $this->temporaryPassword = $userService->resetPassword($owner, $user, $passwordService);
        $this->temporaryPasswordUsername = $user->username;

        session()->flash('status', __('user.manage.password_reset'));
    }

    public function dismissTemporaryPassword(): void
    {
        $this->temporaryPassword = null;
        $this->temporaryPasswordUsername = null;
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function users()
    {
        $owner = auth()->user();

        if ($owner === null) {
            return collect();
        }

        return app(UserService::class)->listForOrganization($owner, [
            'search' => $this->search,
            'center_id' => $this->centerFilter !== '' ? (int) $this->centerFilter : null,
            'role' => $this->roleFilter !== '' ? $this->roleFilter : null,
            'status' => $this->statusFilter,
        ]);
    }

    /**
     * @return Collection<int, Center>
     */
    #[Computed]
    public function centers()
    {
        $owner = auth()->user();

        if ($owner === null) {
            return collect();
        }

        return Center::query()
            ->where('organization_id', $owner->organization_id)
            ->orderBy('name')
            ->get();
    }

    public function render(UserService $userService): View
    {
        return view('livewire.users.manage-users', [
            'roleLabel' => static fn (User $user): string => $userService->roleLabel($user),
        ])->title(__('user.manage.title'));
    }
}
