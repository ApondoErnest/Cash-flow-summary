<?php

declare(strict_types=1);

use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Support\Center\ActiveCenterContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('owner without active center is redirected from operational routes to center selection', function () {
    actingAsOwnerWithoutActiveCenter();

    $this->get(route('dashboard'))
        ->assertRedirect(route('center.select'));
});

test('owner can access center selection without an active center', function () {
    actingAsOwnerWithoutActiveCenter();

    $this->get(route('center.select'))
        ->assertOk()
        ->assertSee(__('pages.center.select'), false);
});

test('owner can access administrative routes without an active center', function () {
    actingAsOwnerWithoutActiveCenter();

    $this->get(route('centers.index'))
        ->assertOk()
        ->assertSee(__('pages.centers.index'), false);
});

test('owner with active center can access operational routes', function () {
    $owner = actingAsOwner();

    $this->get(route('dashboard'))
        ->assertOk();

    $this->get(route('imports.create'))
        ->assertOk();
});

test('owner with tampered center_id on operational route is blocked', function () {
    actingAsOwner();
    $otherCenter = createTestCenter();

    $this->get(route('dashboard', ['center_id' => $otherCenter->id]))
        ->assertForbidden();
});

test('inactive active center is cleared and owner is redirected with status message', function () {
    $owner = actingAsOwner();
    $center = $owner->organization->centers()->firstOrFail();

    $center->forceFill(['is_active' => false])->save();

    $this->get(route('dashboard'))
        ->assertRedirect(route('center.select'))
        ->assertSessionHas('status', __('center.active_center_cleared'));

    expect(app(ActiveCenterContextService::class)->resolve($owner))->toBeNull();
});

test('manager bypasses owner active center middleware', function () {
    actingAsManager();

    app(ActiveCenterContextService::class)->clear();

    $this->get(route('dashboard'))
        ->assertOk();
});

test('active center context is attached to the request for owner operational routes', function () {
    $owner = actingAsOwner();
    $middleware = app(\App\Http\Middleware\EnsureOwnerActiveCenter::class);

    $request = Request::create('/dashboard');
    $route = new \Illuminate\Routing\Route('GET', '/dashboard', fn () => null);
    $route->name('dashboard');
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);
    $request->setUserResolver(fn () => $owner);

    $middleware->handle($request, function ($req) use ($owner) {
        $context = $req->attributes->get('active_center');

        expect($context)->toBeInstanceOf(ActiveCenterContext::class)
            ->and($context->centerId)->toBe(
                app(ActiveCenterContextService::class)->resolve($owner)?->centerId
            );

        return response('ok');
    });
});

test('active center context service rejects centers outside owner organization', function () {
    $owner = actingAsOwner();

    $foreignOrg = \App\Modules\Centers\Models\Organization::query()->create([
        'name' => 'Foreign Organization',
        'code' => 'FRN-'.uniqid(),
    ]);
    $foreignCenter = createTestCenter($foreignOrg);

    app(ActiveCenterContextService::class)->set($owner, $foreignCenter);
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);
