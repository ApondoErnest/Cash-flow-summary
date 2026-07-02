<?php

use App\Models\User;
use App\Modules\AuditLogging\Models\AuditLog;

function createAuditLog(?int $centerId, string $event = 'test.event'): AuditLog
{
    return AuditLog::query()->withoutCenterScope()->create([
        'user_id' => User::query()->value('id'),
        'center_id' => $centerId,
        'event' => $event,
        'resource_type' => 'test',
        'resource_id' => 1,
        'created_at' => now(),
    ]);
}

/**
 * @return list<string>
 */
function operationalRouteNames(): array
{
    return config('owner_active_center.operational_route_names', []);
}
