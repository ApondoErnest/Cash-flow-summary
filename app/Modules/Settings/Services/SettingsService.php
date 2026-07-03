<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Models\User;
use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\Centers\Models\Organization;
use App\Modules\Settings\Enums\OrganizationSettingKey;
use App\Modules\Settings\Models\OrganizationSetting;
use App\Modules\Settings\Support\WhatsAppSettingsData;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class SettingsService
{
    public function get(int $organizationId, OrganizationSettingKey|string $key): ?string
    {
        $keyValue = $key instanceof OrganizationSettingKey ? $key->value : $key;

        $setting = OrganizationSetting::query()
            ->where('organization_id', $organizationId)
            ->where('key', $keyValue)
            ->first();

        if ($setting === null || $setting->value === null || $setting->value === '') {
            return null;
        }

        return $this->decryptValue($keyValue, $setting->value);
    }

    public function set(int $organizationId, User $user, OrganizationSettingKey|string $key, ?string $value): void
    {
        $keyValue = $key instanceof OrganizationSettingKey ? $key->value : $key;

        if ($value === null || $value === '') {
            OrganizationSetting::query()
                ->where('organization_id', $organizationId)
                ->where('key', $keyValue)
                ->delete();

            return;
        }

        OrganizationSetting::query()->updateOrCreate(
            [
                'organization_id' => $organizationId,
                'key' => $keyValue,
            ],
            [
                'value' => $this->encryptValue($keyValue, $value),
                'updated_by' => $user->id,
            ],
        );
    }

    public function whatsAppSettings(int $organizationId): WhatsAppSettingsData
    {
        return new WhatsAppSettingsData(
            ownerPhone: $this->get($organizationId, OrganizationSettingKey::WhatsappOwnerPhone),
            phoneNumberId: $this->get($organizationId, OrganizationSettingKey::WhatsappPhoneNumberId),
            accessTokenConfigured: $this->get($organizationId, OrganizationSettingKey::WhatsappAccessToken) !== null,
            webhookVerifyTokenConfigured: $this->get($organizationId, OrganizationSettingKey::WhatsappWebhookVerifyToken) !== null,
        );
    }

    /**
     * @param  array{
     *     owner_phone: string,
     *     phone_number_id: string,
     *     access_token?: string|null,
     *     webhook_verify_token?: string|null,
     * }  $payload
     */
    public function updateWhatsAppSettings(Organization $organization, User $user, array $payload): WhatsAppSettingsData
    {
        return DB::transaction(function () use ($organization, $user, $payload): WhatsAppSettingsData {
            $this->set(
                (int) $organization->id,
                $user,
                OrganizationSettingKey::WhatsappOwnerPhone,
                $this->normalizePhone($payload['owner_phone']),
            );

            $this->set(
                (int) $organization->id,
                $user,
                OrganizationSettingKey::WhatsappPhoneNumberId,
                trim($payload['phone_number_id']),
            );

            if (filled($payload['access_token'] ?? null)) {
                $this->set(
                    (int) $organization->id,
                    $user,
                    OrganizationSettingKey::WhatsappAccessToken,
                    trim((string) $payload['access_token']),
                );
            }

            if (filled($payload['webhook_verify_token'] ?? null)) {
                $this->set(
                    (int) $organization->id,
                    $user,
                    OrganizationSettingKey::WhatsappWebhookVerifyToken,
                    trim((string) $payload['webhook_verify_token']),
                );
            }

            AuditLog::query()->create([
                'user_id' => $user->id,
                'center_id' => null,
                'event' => 'settings.updated',
                'resource_type' => OrganizationSetting::class,
                'resource_id' => (int) $organization->id,
                'new_values' => [
                    'scope' => 'whatsapp',
                    'owner_phone' => $this->normalizePhone($payload['owner_phone']),
                    'phone_number_id' => trim($payload['phone_number_id']),
                    'access_token' => filled($payload['access_token'] ?? null) ? '[updated]' : '[unchanged]',
                    'webhook_verify_token' => filled($payload['webhook_verify_token'] ?? null) ? '[updated]' : '[unchanged]',
                ],
            ]);

            return $this->whatsAppSettings((int) $organization->id);
        });
    }

    private function normalizePhone(string $phone): string
    {
        $normalized = preg_replace('/\s+/', '', trim($phone));

        return $normalized ?? trim($phone);
    }

    private function encryptValue(string $key, string $value): string
    {
        if ($this->isEncryptedKey($key)) {
            return Crypt::encryptString($value);
        }

        return $value;
    }

    private function decryptValue(string $key, string $value): string
    {
        if (! $this->isEncryptedKey($key)) {
            return $value;
        }

        return Crypt::decryptString($value);
    }

    private function isEncryptedKey(string $key): bool
    {
        return in_array($key, config('organization_settings.encrypted_keys', []), true);
    }
}
