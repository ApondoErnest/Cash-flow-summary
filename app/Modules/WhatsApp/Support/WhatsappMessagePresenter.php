<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Support;

use App\Modules\WhatsApp\Enums\WhatsappMessageStatus;

final class WhatsappMessagePresenter
{
    /**
     * @return array{label: string, variant: string}
     */
    public static function statusBadge(WhatsappMessageStatus $status): array
    {
        return match ($status) {
            WhatsappMessageStatus::Queued => [
                'label' => __('whatsapp.status.queued'),
                'variant' => 'info',
            ],
            WhatsappMessageStatus::Sent => [
                'label' => __('whatsapp.status.sent'),
                'variant' => 'success',
            ],
            WhatsappMessageStatus::Delivered => [
                'label' => __('whatsapp.status.delivered'),
                'variant' => 'success',
            ],
            WhatsappMessageStatus::Read => [
                'label' => __('whatsapp.status.read'),
                'variant' => 'success',
            ],
            WhatsappMessageStatus::Failed => [
                'label' => __('whatsapp.status.failed'),
                'variant' => 'error',
            ],
        };
    }

    public static function eventTypeLabel(string $eventType): string
    {
        $key = 'whatsapp.event_type.'.$eventType;

        return __($key) === $key ? $eventType : __($key);
    }
}
