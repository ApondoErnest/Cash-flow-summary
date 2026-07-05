<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Exceptions;

use RuntimeException;

final class WhatsAppNotConfiguredException extends RuntimeException
{
    public static function forOrganization(int $organizationId): self
    {
        return new self("WhatsApp outbound credentials are not configured for organization [{$organizationId}].");
    }
}
