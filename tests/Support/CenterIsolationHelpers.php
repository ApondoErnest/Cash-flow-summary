<?php

use App\Models\User;
use App\Modules\AuditLogging\Models\AuditLog;

function createAuditLog(?int $centerId, string $event = 'test.event', ?User $user = null, array $attributes = []): AuditLog
{
    return AuditLog::query()->withoutCenterScope()->create(array_merge([
        'user_id' => $user?->id ?? User::query()->value('id'),
        'center_id' => $centerId,
        'event' => $event,
        'resource_type' => 'test',
        'resource_id' => 1,
        'created_at' => now(),
    ], $attributes));
}

/**
 * @return list<string>
 */
function operationalRouteNames(): array
{
    /** @var array{operational_route_names?: list<string>} $config */
    $config = require dirname(__DIR__, 2).'/config/owner_active_center.php';

    return $config['operational_route_names'] ?? [];
}

/**
 * Minimal path parameters so operational routes can be generated in isolation smoke tests.
 *
 * @return array<string, int|string>
 */
function operationalRoutePathParameters(string $routeName): array
{
    return match ($routeName) {
        'imports.show', 'imports.result', 'imports.errors.download' => ['import' => 1],
        'exports.download' => ['exportRequest' => 1],
        'verifications.errors.download' => ['token' => 'smoke-test-token'],
        default => [],
    };
}

/**
 * @return list<array{0: string, 1: array<string, int|string>}>
 */
function operationalRouteSmokeCases(): array
{
    $skipRouteNames = [
        // Signed download routes — center isolation covered in FileDownloadAuthorizationTest.
        'imports.errors.download',
        'verifications.errors.download',
        'exports.download',
        // Detail routes need a real import id; dummy ids 404 before center middleware applies.
        'imports.show',
        'imports.result',
    ];

    return array_map(
        fn (string $routeName) => [$routeName, operationalRoutePathParameters($routeName)],
        array_values(array_filter(
            operationalRouteNames(),
            fn (string $routeName) => ! in_array($routeName, $skipRouteNames, true),
        )),
    );
}
