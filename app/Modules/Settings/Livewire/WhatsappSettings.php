<?php

declare(strict_types=1);

namespace App\Modules\Settings\Livewire;

use App\Modules\Centers\Models\Organization;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\Settings\Support\WhatsAppSettingsData;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class WhatsappSettings extends Component
{
    public Organization $organization;

    public string $ownerPhone = '';

    public string $phoneNumberId = '';

    public string $accessToken = '';

    public string $webhookVerifyToken = '';

    public bool $accessTokenConfigured = false;

    public bool $webhookVerifyTokenConfigured = false;

    public function mount(SettingsService $settingsService): void
    {
        $owner = auth()->user();

        abort_unless($owner?->isOwner(), 403, __('center.owner_only'));

        $organization = $owner->organization;

        abort_if($organization === null, 404);

        $this->organization = $organization;

        $this->fillFromSettings($settingsService->whatsAppSettings((int) $organization->id));
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

        $settings = $settingsService->updateWhatsAppSettings(
            organization: $this->organization,
            user: $owner,
            payload: [
                'owner_phone' => $validated['ownerPhone'],
                'phone_number_id' => $validated['phoneNumberId'],
                'access_token' => $validated['accessToken'] !== '' ? $validated['accessToken'] : null,
                'webhook_verify_token' => $validated['webhookVerifyToken'] !== '' ? $validated['webhookVerifyToken'] : null,
            ],
        );

        $this->fillFromSettings($settings);

        session()->flash('status', __('settings.whatsapp.saved'));
    }

    public function render(): View
    {
        return view('livewire.settings.whatsapp-settings', [
            'isConfigured' => $this->accessTokenConfigured
                && $this->webhookVerifyTokenConfigured
                && $this->ownerPhone !== ''
                && $this->phoneNumberId !== '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'ownerPhone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'phoneNumberId' => ['required', 'string', 'max:255', 'regex:/^\d+$/'],
            'accessToken' => [
                $this->accessTokenConfigured ? 'nullable' : 'required',
                'string',
                'min:20',
            ],
            'webhookVerifyToken' => [
                $this->webhookVerifyTokenConfigured ? 'nullable' : 'required',
                'string',
                'min:8',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        return [
            'ownerPhone' => __('settings.whatsapp.fields.owner_phone'),
            'phoneNumberId' => __('settings.whatsapp.fields.phone_number_id'),
            'accessToken' => __('settings.whatsapp.fields.access_token'),
            'webhookVerifyToken' => __('settings.whatsapp.fields.webhook_verify_token'),
        ];
    }

    private function fillFromSettings(WhatsAppSettingsData $settings): void
    {
        $this->ownerPhone = $settings->ownerPhone ?? '';
        $this->phoneNumberId = $settings->phoneNumberId ?? '';
        $this->accessToken = '';
        $this->webhookVerifyToken = '';
        $this->accessTokenConfigured = $settings->accessTokenConfigured;
        $this->webhookVerifyTokenConfigured = $settings->webhookVerifyTokenConfigured;
    }
}
