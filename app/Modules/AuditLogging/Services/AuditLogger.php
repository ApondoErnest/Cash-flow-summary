<?php

declare(strict_types=1);

namespace App\Modules\AuditLogging\Services;

use App\Models\User;
use App\Modules\AuditLogging\Models\AuditLog;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        string $event,
        ?User $user = null,
        ?int $centerId = null,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
    ): AuditLog {
        $request = request();

        return AuditLog::query()->withoutCenterScope()->create([
            'user_id' => $user?->id,
            'center_id' => $centerId,
            'event' => $event,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
