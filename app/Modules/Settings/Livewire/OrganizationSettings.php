<?php

declare(strict_types=1);

namespace App\Modules\Settings\Livewire;

use App\Modules\Centers\Models\Organization;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\Settings\Support\OrganizationProfileData;
use App\Support\Locale\AppLocale;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class OrganizationSettings extends Component
{
    public Organization $organization;

    public string $name = '';

    public string $code = '';

    public string $defaultLanguage = 'fr';

    public string $contactEmail = '';

    public string $contactPhone = '';

    public function mount(SettingsService $settingsService): void
    {
        $owner = auth()->user();

        abort_unless($owner?->isOwner(), 403, __('center.owner_only'));

        $organization = $owner->organization;

        abort_if($organization === null, 404);

        $this->organization = $organization;

        $this->fillFromProfile($settingsService->organizationProfile($organization));
    }

    public function save(SettingsService $settingsService): void
    {
        $owner = auth()->user();

        abort_unless($owner?->isOwner(), 403, __('center.owner_only'));

        $validated = $this->validate(
            $this->rules(),
            [],
            $this->validationAttributes(),
        );

        $this->organization = $settingsService->updateOrganizationProfile(
            organization: $this->organization,
            user: $owner,
            payload: [
                'name' => $validated['name'],
                'code' => $validated['code'],
                'default_language' => $validated['defaultLanguage'],
                'contact_email' => $validated['contactEmail'] !== '' ? $validated['contactEmail'] : null,
                'contact_phone' => $validated['contactPhone'] !== '' ? $validated['contactPhone'] : null,
            ],
        );

        $this->fillFromProfile($settingsService->organizationProfile($this->organization));

        session()->flash('status', __('settings.organization.saved'));
    }

    public function render(): View
    {
        return view('livewire.settings.organization-settings');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash:ascii',
                Rule::unique('organizations', 'code')->ignore($this->organization->id),
            ],
            'defaultLanguage' => ['required', 'in:'.implode(',', AppLocale::supported())],
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        return [
            'name' => __('settings.organization.fields.name'),
            'code' => __('settings.organization.fields.code'),
            'defaultLanguage' => __('settings.organization.fields.default_language'),
            'contactEmail' => __('settings.organization.fields.contact_email'),
            'contactPhone' => __('settings.organization.fields.contact_phone'),
        ];
    }

    private function fillFromProfile(OrganizationProfileData $profile): void
    {
        $this->name = $profile->name;
        $this->code = $profile->code;
        $this->defaultLanguage = $profile->defaultLanguage;
        $this->contactEmail = $profile->contactEmail ?? '';
        $this->contactPhone = $profile->contactPhone ?? '';
    }
}
