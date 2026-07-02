<?php

declare(strict_types=1);

use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\AssignedCenterService;
use App\Support\Center\AssignedCenterContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('owner bypasses assigned center middleware', function () {
    actingAsOwner();

    $this->get(route('dashboard'))
        ->assertOk();
});

test('manager with assigned center can access dashboard', function () {
    $manager = actingAsManager();

    $this->get(route('dashboard'))
        ->assertOk();

    $this->get(route('dashboard', ['center_id' => $manager->center_id]))
        ->assertOk();
});

test('cashier with assigned center can access dashboard', function () {
    actingAsCashier();

    $this->get(route('dashboard'))
        ->assertOk();
});

test('manager without assigned center is blocked', function () {
    $manager = actingAsManager();
    $manager->forceFill(['center_id' => null])->save();

    $this->get(route('dashboard'))
        ->assertForbidden();
});

test('manager with tampered center_id in query is blocked', function () {
    actingAsManager();
    $otherCenter = createTestCenter();

    $this->get(route('dashboard', ['center_id' => $otherCenter->id]))
        ->assertForbidden();
});

test('manager with tampered center_id in request body is rejected by assigned center service', function () {
    $manager = actingAsManager();
    $otherCenter = createTestCenter();

    $request = Request::create(route('dashboard'), 'POST', ['center_id' => $otherCenter->id]);
    $request->setUserResolver(fn () => $manager);

    $service = app(AssignedCenterService::class);

    expect($service->requestIsScopedToAssignedCenter($manager, $request))->toBeFalse();
});

test('manager with inactive assigned center is blocked', function () {
    $center = createTestCenter(attributes: ['is_active' => false]);

    actingAsManager($center);

    $this->get(route('dashboard'))
        ->assertForbidden();
});

test('assigned center context is attached to the request for center staff', function () {
    $manager = actingAsManager();
    $middleware = app(\App\Http\Middleware\EnsureAssignedCenter::class);

    $request = Request::create(route('dashboard'));
    $request->setUserResolver(fn () => $manager);

    $middleware->handle($request, function ($req) use ($manager) {
        $context = $req->attributes->get('assigned_center');

        expect($context)->toBeInstanceOf(AssignedCenterContext::class)
            ->and($context->centerId)->toBe((int) $manager->center_id);

        return response('ok');
    });
});
