<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Support;

use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\Organization;

final class WhatsAppTimezone
{
    /**
     * Owner/org business timezone for scheduled summary evaluation.
     */
    public static function forCenter(Center $center): string
    {
        $center->loadMissing('organization');

        return self::forOrganization($center->organization);
    }

    public static function forOrganization(?Organization $organization): string
    {
        $timezone = is_string($organization?->timezone) ? trim($organization->timezone) : '';

        if ($timezone !== '') {
            return $timezone;
        }

        return (string) config('app.timezone', 'Africa/Douala');
    }
}
