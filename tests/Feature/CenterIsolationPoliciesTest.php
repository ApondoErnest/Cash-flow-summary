<?php

declare(strict_types=1);

use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\Organization;
use App\Policies\CenterResourcePolicy;
use App\Support\Center\CenterContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('center context resolver resolves owner active center from session', function () {
    $owner = actingAsOwner();

    $context = app(CenterContextResolver::class)->resolve($owner);

    expect($context)->not->toBeNull()
        ->and($context->source)->toBe('active');
});

test('center context resolver resolves manager assigned center', function () {
    $manager = actingAsManager();

    $context = app(CenterContextResolver::class)->resolve($manager);

    expect($context)->not->toBeNull()
        ->and($context->centerId)->toBe((int) $manager->center_id)
        ->and($context->source)->toBe('assigned');
});

test('operational scope is not applied for owner on admin routes', function () {
    actingAsOwner();

    $request = Request::create('/centers');
    $route = new Route('GET', '/centers', fn () => null);
    $route->name('centers.index');
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);

    app()->instance('request', $request);

    expect(app(CenterContextResolver::class)->shouldApplyOperationalScope())->toBeFalse();
});

test('operational scope is applied for owner on operational routes', function () {
    actingAsOwner();

    $request = Request::create('/imports');
    $route = new Route('GET', '/imports', fn () => null);
    $route->name('imports.index');
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);

    app()->instance('request', $request);

    expect(app(CenterContextResolver::class)->shouldApplyOperationalScope())->toBeTrue();
});

test('center scope filters audit logs to manager assigned center', function () {
    $manager = actingAsManager();
    $otherCenter = createTestCenter($manager->organization);

    createAuditLog($manager->center_id, 'manager.event');
    createAuditLog($otherCenter->id, 'other.event');

    expect(AuditLog::query()->count())->toBe(1)
        ->and(AuditLog::query()->value('event'))->toBe('manager.event');
});

test('center scope filters audit logs to owner active center', function () {
    $owner = actingAsOwner();
    $activeCenterId = app(CenterContextResolver::class)->resolve($owner)?->centerId;
    $otherCenter = createTestCenter($owner->organization);

    createAuditLog($activeCenterId, 'active.event');
    createAuditLog($otherCenter->id, 'other.event');

    expect(AuditLog::query()->count())->toBe(1)
        ->and(AuditLog::query()->value('event'))->toBe('active.event');
});

test('owner can view any center in organization via center policy', function () {
    $owner = actingAsOwner();
    $foreignCenter = createTestCenter(Organization::query()->create([
        'name' => 'Foreign Organization',
        'code' => 'FRN-'.uniqid(),
    ]));

    expect(Gate::forUser($owner)->allows('view', $owner->organization->centers()->first()))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('view', $foreignCenter))->toBeFalse();
});

test('manager can only view assigned center via center policy', function () {
    $manager = actingAsManager();
    $otherCenter = createTestCenter($manager->organization);

    expect(Gate::forUser($manager)->allows('view', $manager->center))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('view', $otherCenter))->toBeFalse()
        ->and(Gate::forUser($manager)->allows('create', Center::class))->toBeFalse();
});

test('audit log policy allows owner admin access org-wide', function () {
    $owner = actingAsOwner();
    $centerA = createTestCenter($owner->organization);
    $centerB = createTestCenter($owner->organization);

    $logA = createAuditLog($centerA->id);
    $logB = createAuditLog($centerB->id);

    $request = Request::create('/audit-logs');
    $route = new Route('GET', '/audit-logs', fn () => null);
    $route->name('audit-logs.index');
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);

    expect(Gate::forUser($owner)->allows('viewAny', AuditLog::class))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('view', $logA))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('view', $logB))->toBeTrue();
});

test('audit log policy scopes owner operational access to active center', function () {
    $owner = actingAsOwner();
    $activeCenterId = app(CenterContextResolver::class)->resolve($owner)?->centerId;
    $otherCenter = createTestCenter($owner->organization);

    $activeLog = createAuditLog($activeCenterId);
    $otherLog = createAuditLog($otherCenter->id);

    $request = Request::create('/imports');
    $route = new Route('GET', '/imports', fn () => null);
    $route->name('imports.index');
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);

    expect(Gate::forUser($owner)->allows('view', $activeLog))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('view', $otherLog))->toBeFalse();
});

test('manager cannot access audit logs', function () {
    $manager = actingAsManager();

    expect(Gate::forUser($manager)->allows('viewAny', AuditLog::class))->toBeFalse();
});

test('center resource policy import requires resolved center context', function () {
    $policy = new class extends CenterResourcePolicy {};

    actingAsManager();
    expect($policy->import(auth()->user()))->toBeTrue();

    actingAsOwnerWithoutActiveCenter();
    expect($policy->import(auth()->user()))->toBeFalse();
});
