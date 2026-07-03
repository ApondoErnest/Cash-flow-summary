<?php

declare(strict_types=1);

namespace App\Modules\Settings\Enums;

enum OrganizationSettingKey: string
{
    case WhatsappOwnerPhone = 'whatsapp.owner_phone';
    case WhatsappPhoneNumberId = 'whatsapp.phone_number_id';
    case WhatsappAccessToken = 'whatsapp.access_token';
    case WhatsappWebhookVerifyToken = 'whatsapp.webhook_verify_token';

    /**
     * @return list<self>
     */
    public static function whatsappKeys(): array
    {
        return [
            self::WhatsappOwnerPhone,
            self::WhatsappPhoneNumberId,
            self::WhatsappAccessToken,
            self::WhatsappWebhookVerifyToken,
        ];
    }
}
