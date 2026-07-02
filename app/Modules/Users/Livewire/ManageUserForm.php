<?php

declare(strict_types=1);

namespace App\Modules\Users\Livewire;

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\Users\Services\UserService;
use App\Support\Auth\RoleName;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ManageUserForm extends Component
{
    use AuthorizesRequests;

    public ?User $user = null;

    #[Locked]
    public bool $isOwnerAccount = false;

    public string $name = '';

    public string $username = '';

    public string $phone = '';

    public string $email = '';

    public string $role = RoleName::CenterManager;

    public ?int $centerId = null;

    public bool $is_active = true;

    public ?string $createdTemporaryPassword = null;

    public function mount(?User $user = null): void
    {
        $this->user = $user;

        if ($user !== null) {
            $this->authorize('update', $user);
            $this->fillFromUser($user);

            return;
        }

        if ($user === null) {
            $this->authorize('create', User::class);

            $firstCenter = Center::query()
                ->where('organization_id', auth()->user()?->organization_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->first();

            $this->centerId = $firstCenter?->id;

            return;
        }
    }

    public function save(UserService $userService): void
    {
        $owner = auth()->user();

        if ($owner === null) {
            return;
        }

        $validated = $this->validate(
            $this->rules(),
            [],
            $this->validationAttributes(),
        );

        if ($this->user === null) {
            $result = $userService->create($owner, [
                'name' => $validated['name'],
                'username' => $validated['username'],
                'phone' => $validated['phone'] !== '' ? $validated['phone'] : null,
                'email' => $validated['email'] !== '' ? $validated['email'] : null,
                'role' => $validated['role'],
                'center_id' => $validated['centerId'],
            ]);

            session()->flash('status', __('user.manage.created'));
            session()->flash('temporary_password', [
                'username' => $result['user']->username,
                'password' => $result['temporary_password'],
            ]);

            $this->redirect(route('users.index'), navigate: true);

            return;
        }

        $payload = [
            'name' => $validated['name'],
            'phone' => $validated['phone'] !== '' ? $validated['phone'] : null,
            'email' => $validated['email'] !== '' ? $validated['email'] : null,
        ];

        if (! $this->isOwnerAccount) {
            $payload['username'] = $validated['username'];
            $payload['role'] = $validated['role'];
            $payload['center_id'] = $validated['centerId'];
            $payload['is_active'] = $validated['is_active'];
        }

        $userService->update($owner, $this->user, $payload);

        session()->flash('status', __('user.manage.updated'));

        $this->redirect(route('users.index'), navigate: true);
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
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function render(UserService $userService): View
    {
        return view('livewire.users.manage-user-form', [
            'isEditing' => $this->user !== null,
            'assignableRoles' => $userService->assignableRoles(),
        ])->title($this->user !== null
            ? __('user.manage.edit_title')
            : __('user.manage.create_title'));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
        ];

        if ($this->isOwnerAccount) {
            return $rules;
        }

        return array_merge($rules, [
            'username' => [
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique('users', 'username')->ignore($this->user?->id),
            ],
            'role' => ['required', 'in:'.implode(',', app(UserService::class)->assignableRoles())],
            'centerId' => ['required', 'integer', 'exists:centers,id'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        return [
            'name' => __('user.manage.fields.name'),
            'username' => __('user.manage.fields.username'),
            'phone' => __('user.manage.fields.phone'),
            'email' => __('user.manage.fields.email'),
            'role' => __('user.manage.fields.role'),
            'centerId' => __('user.manage.fields.center'),
            'is_active' => __('user.manage.fields.is_active'),
        ];
    }

    private function fillFromUser(User $user): void
    {
        $this->isOwnerAccount = $user->isOwner();
        $this->name = $user->name;
        $this->username = $user->username;
        $this->phone = (string) ($user->phone ?? '');
        $this->email = (string) ($user->email ?? '');
        $this->role = $user->roles->first()?->name ?? RoleName::CenterManager;
        $this->centerId = $user->center_id !== null ? (int) $user->center_id : null;
        $this->is_active = $user->is_active;
    }
}
