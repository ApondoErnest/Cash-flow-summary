<?php

declare(strict_types=1);

use App\Modules\AuditLogging\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

dataset('operational_route_names', fn () => array_map(
    fn (string $routeName) => [$routeName],
    operationalRouteNames(),
));

describe('checkpoint AC #5 / #53 — cross-center tampering blocked', function () {
    test('manager receives 403 when tampering center_id on operational routes', function (string $routeName) {
        actingAsManager();
        $otherCenter = createTestCenter();

        $this->get(route($routeName, ['center_id' => $otherCenter->id]))
            ->assertForbidden();
    })->with('operational_route_names');

    test('cashier receives 403 when tampering center_id on operational routes', function (string $routeName) {
        actingAsCashier();
        $otherCenter = createTestCenter();

        $this->get(route($routeName, ['center_id' => $otherCenter->id]))
            ->assertForbidden();
    })->with('operational_route_names');

    test('owner receives 403 when tampering center_id on operational routes', function (string $routeName) {
        actingAsOwner();
        $otherCenter = createTestCenter();

        $this->get(route($routeName, ['center_id' => $otherCenter->id]))
            ->assertForbidden();
    })->with('operational_route_names');
});

describe('checkpoint AC #3 — single assigned center for staff', function () {
    test('manager account is bound to exactly one center', function () {
        $manager = actingAsManager();

        expect($manager->center_id)->not->toBeNull()
            ->and($manager->center)->toBeInstanceOf(\App\Modules\Centers\Models\Center::class)
            ->and($manager->center->is_active)->toBeTrue();
    });

    test('cashier account is bound to exactly one center', function () {
        $cashier = actingAsCashier();

        expect($cashier->center_id)->not->toBeNull()
            ->and($cashier->center)->toBeInstanceOf(\App\Modules\Centers\Models\Center::class)
            ->and($cashier->center->is_active)->toBeTrue();
    });
});

describe('checkpoint AC #4 / #52 — staff never use owner center selection UI', function () {
    test('manager cannot access owner center selection page', function () {
        actingAsManager();

        $this->get(route('center.select'))->assertForbidden();
    });

    test('cashier cannot access owner center selection page', function () {
        actingAsCashier();

        $this->get(route('center.select'))->assertForbidden();
    });

    test('manager dashboard shows assigned center without owner switcher', function () {
        $manager = actingAsManager();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('navigation.shell.assigned_center'), false)
            ->assertSee($manager->center->name, false)
            ->assertDontSee(__('navigation.shell.switch_center'), false);
    });

    test('cashier dashboard shows assigned center without owner switcher', function () {
        $cashier = actingAsCashier();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('navigation.shell.assigned_center'), false)
            ->assertSee($cashier->center->name, false)
            ->assertDontSee(__('navigation.shell.switch_center'), false);
    });

    test('owner dashboard shows active center with owner switcher', function () {
        $owner = actingAsOwner();
        $centerName = app(\App\Support\Center\CenterContextResolver::class)
            ->resolve($owner)?->centerName;

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('navigation.shell.active_center'), false)
            ->assertSee($centerName, false)
            ->assertSee(__('navigation.shell.switch_center'), false)
            ->assertSee(__('auth.logout'), false);
    });
});

describe('checkpoint AC #1 — owner administration access', function () {
    test('owner can access manage centers without an active center', function () {
        actingAsOwnerWithoutActiveCenter();

        $this->get(route('centers.index'))
            ->assertOk()
            ->assertSee(__('pages.centers.index'), false);
    });

    test('owner can access manage users without an active center', function () {
        actingAsOwnerWithoutActiveCenter();

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee(__('pages.users.index'), false);
    });
});

describe('checkpoint gate — HTTP isolation smoke', function () {
    test('manager operational page request keeps database queries center-scoped', function () {
        $manager = actingAsManager();
        $otherCenter = createTestCenter($manager->organization);

        createAuditLog($manager->center_id, 'manager.event');
        createAuditLog($otherCenter->id, 'other.event');

        $this->get(route('imports.index'))->assertOk();

        expect(AuditLog::query()->count())->toBe(1)
            ->and(AuditLog::query()->value('event'))->toBe('manager.event');
    });

    test('owner admin page request keeps database queries organization-wide', function () {
        $owner = actingAsOwner();
        $centerA = createTestCenter($owner->organization);
        $centerB = createTestCenter($owner->organization);

        createAuditLog($centerA->id, 'center-a.event');
        createAuditLog($centerB->id, 'center-b.event');

        $this->get(route('centers.index'))->assertOk();

        expect(AuditLog::query()->count())->toBe(2);
    });

    test('owner without active center is redirected from dashboard to center selection', function () {
        actingAsOwnerWithoutActiveCenter();

        $this->get(route('dashboard'))
            ->assertRedirect(route('center.select'));
    });

    test('owner with active center can sign in flow reach operational dashboard', function () {
        actingAsOwner();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('navigation.shell.active_center'), false);
    });
});
