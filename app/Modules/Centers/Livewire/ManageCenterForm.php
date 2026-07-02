<?php

declare(strict_types=1);

namespace App\Modules\Centers\Livewire;

use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\CenterService;
use App\Modules\Centers\Services\OwnerPreferredCenterService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ManageCenterForm extends Component
{
    use AuthorizesRequests;

    public ?Center $center = null;

    public string $name = '';

    public string $code = '';

    public string $address = '';

    public string $city = '';

    public string $region = '';

    public string $phone = '';

    public string $default_language = 'fr';

    public string $submission_deadline = '';

    public bool $is_active = true;

    public bool $setAsDefault = false;

    public function mount(?Center $center = null): void
    {
        $this->center = $center;

        if ($center !== null) {
            $this->authorize('update', $center);
            $this->fillFromCenter($center);

            return;
        }

        $this->authorize('create', Center::class);
        $this->setAsDefault = Center::query()
            ->where('organization_id', auth()->user()?->organization_id)
            ->where('is_active', true)
            ->doesntExist();
    }

    public function save(
        CenterService $centerService,
        OwnerPreferredCenterService $ownerPreferredCenterService,
    ): void {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $validated = $this->validate(
            $this->rules(),
            [],
            $this->validationAttributes(),
        );

        $payload = [
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'address' => $validated['address'] !== '' ? $validated['address'] : null,
            'city' => $validated['city'] !== '' ? $validated['city'] : null,
            'region' => $validated['region'] !== '' ? $validated['region'] : null,
            'phone' => $validated['phone'] !== '' ? $validated['phone'] : null,
            'default_language' => $validated['default_language'],
            'submission_deadline' => $validated['submission_deadline'] !== '' ? $validated['submission_deadline'] : null,
            'is_active' => $this->center !== null ? $validated['is_active'] : true,
        ];

        if ($this->center === null) {
            $this->authorize('create', Center::class);
            $center = $centerService->create($user, $payload);

            if ($this->setAsDefault) {
                $ownerPreferredCenterService->setPreferred($user, $center);
            }

            session()->flash('status', __('center.manage.created'));

            $this->redirect(route('centers.index'), navigate: true);

            return;
        }

        $this->authorize('update', $this->center);
        $centerService->update($this->center, $user, $payload);

        if ($this->setAsDefault) {
            $ownerPreferredCenterService->setPreferred($user, $this->center->fresh());
        }

        session()->flash('status', __('center.manage.updated'));

        $this->redirect(route('centers.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.centers.manage-center-form', [
            'isEditing' => $this->center !== null,
        ])->title($this->center !== null
            ? __('center.manage.edit_title')
            : __('center.manage.create_title'));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $organizationId = auth()->user()?->organization_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash:ascii',
                Rule::unique('centers', 'code')
                    ->where(fn ($query) => $query->where('organization_id', $organizationId))
                    ->ignore($this->center?->id),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'default_language' => ['required', 'in:fr,en'],
            'submission_deadline' => ['nullable', 'date_format:H:i'],
            'is_active' => ['boolean'],
            'setAsDefault' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        return [
            'name' => __('center.manage.fields.name'),
            'code' => __('center.manage.fields.code'),
            'address' => __('center.manage.fields.address'),
            'city' => __('center.manage.fields.city'),
            'region' => __('center.manage.fields.region'),
            'phone' => __('center.manage.fields.phone'),
            'default_language' => __('center.manage.fields.default_language'),
            'submission_deadline' => __('center.manage.fields.submission_deadline'),
        ];
    }

    private function fillFromCenter(Center $center): void
    {
        $this->name = $center->name;
        $this->code = $center->code;
        $this->address = (string) ($center->address ?? '');
        $this->city = (string) ($center->city ?? '');
        $this->region = (string) ($center->region ?? '');
        $this->phone = (string) ($center->phone ?? '');
        $this->default_language = $center->default_language ?? 'fr';
        $this->submission_deadline = $center->submission_deadline !== null
            ? substr((string) $center->submission_deadline, 0, 5)
            : '';
        $this->is_active = $center->is_active;
        $this->setAsDefault = (int) auth()->user()?->preferred_center_id === (int) $center->id;
    }
}
