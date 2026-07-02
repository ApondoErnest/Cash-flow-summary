<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Authentication\Livewire\ChangePassword;
use App\Modules\Authentication\Livewire\Login;
use App\Modules\Authentication\Livewire\TwoFactorChallenge;
use App\Modules\Authentication\Services\AuthenticationRedirectService;
use App\Modules\Authentication\Services\TwoFactorService;
use App\Modules\Centers\Livewire\CenterSelection;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\Centers\Services\OwnerPreferredCenterService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('owner with preferred center is redirected to dashboard after login bootstrap', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    $owner->forceFill(['must_change_password' => false])->save();
    $center = createTestCenter($owner->organization, ['name' => 'Preferred Center']);
    setOwnerPreferredCenter($owner, $center);

    $route = app(AuthenticationRedirectService::class)->nextRoute($owner->fresh());

    expect($route)->toBe(route('dashboard'));
    expect(app(ActiveCenterContextService::class)->resolve($owner->fresh())?->centerId)->toBe($center->id);
});

test('owner with a single active center skips selection without a stored preference', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    $owner->forceFill(['must_change_password' => false])->save();
    $center = createTestCenter($owner->organization, ['name' => 'Only Center']);

    $route = app(AuthenticationRedirectService::class)->nextRoute($owner->fresh());

    expect($route)->toBe(route('dashboard'));
    expect(app(ActiveCenterContextService::class)->resolve($owner->fresh())?->centerId)->toBe($center->id);
});

test('owner with multiple centers and no preference is redirected to center selection', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    $owner->forceFill(['must_change_password' => false])->save();
    createTestCenter($owner->organization, ['name' => 'Center A']);
    createTestCenter($owner->organization, ['name' => 'Center B']);

    $route = app(AuthenticationRedirectService::class)->nextRoute($owner->fresh());

    expect($route)->toBe(route('center.select'));
    expect(app(ActiveCenterContextService::class)->resolve($owner->fresh()))->toBeNull();
});

test('owner can save preferred center from center selection page', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    $owner->forceFill(['must_change_password' => false])->save();
    $this->actingAs($owner);

    $center = createTestCenter($owner->organization, ['name' => 'Saved Default']);

    Livewire::test(CenterSelection::class)
        ->set('centerId', $center->id)
        ->set('rememberAsDefault', true)
        ->call('openCenter')
        ->assertRedirect(route('dashboard'));

    expect($owner->fresh()->preferred_center_id)->toBe($center->id);
});

test('center selection does not overwrite preferred center when remember default is unchecked', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    $owner->forceFill(['must_change_password' => false])->save();
    $this->actingAs($owner);

    $preferred = createTestCenter($owner->organization, ['name' => 'Preferred Center']);
    $temporary = createTestCenter($owner->organization, ['name' => 'Temporary Center']);
    setOwnerPreferredCenter($owner, $preferred);

    Livewire::test(CenterSelection::class)
        ->set('centerId', $temporary->id)
        ->set('rememberAsDefault', false)
        ->call('openCenter')
        ->assertRedirect(route('dashboard'));

    expect($owner->fresh()->preferred_center_id)->toBe($preferred->id);
});

test('owner with preferred center reaches dashboard after password change', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    $center = createTestCenter($owner->organization);
    setOwnerPreferredCenter($owner, $center);

    $this->actingAs($owner);

    Livewire::test(ChangePassword::class)
        ->set('currentPassword', env('SEED_OWNER_PASSWORD', 'password'))
        ->set('password', 'Str0ng!Passw0rd')
        ->set('password_confirmation', 'Str0ng!Passw0rd')
        ->call('updatePassword')
        ->assertRedirect(route('dashboard'));
});

test('owner with preferred center reaches dashboard after two-factor verification', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    $owner->forceFill(['must_change_password' => false])->save();
    $center = createTestCenter($owner->organization);
    setOwnerPreferredCenter($owner, $center);
    $secret = enableTwoFactorFor($owner);

    Livewire::test(Login::class)
        ->set('username', $owner->username)
        ->set('password', env('SEED_OWNER_PASSWORD', 'password'))
        ->call('authenticate');

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', currentTwoFactorCode($secret))
        ->call('verify')
        ->assertRedirect(route('dashboard'));

    expect(app(TwoFactorService::class)->isVerified())->toBeTrue();
});

test('invalid preferred center is cleared during login bootstrap', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    $owner->forceFill(['must_change_password' => false])->save();
    $center = createTestCenter($owner->organization);
    setOwnerPreferredCenter($owner, $center);
    $center->forceFill(['is_active' => false])->save();

    app(OwnerPreferredCenterService::class)->bootstrapActiveCenter($owner->fresh());

    expect($owner->fresh()->preferred_center_id)->toBeNull();
    expect(app(ActiveCenterContextService::class)->resolve($owner->fresh()))->toBeNull();
});

test('deactivated active center clears invalid preferred center on redirect', function () {
    $owner = actingAsOwner();
    $center = $owner->organization->centers()->firstOrFail();
    setOwnerPreferredCenter($owner, $center);

    $center->forceFill(['is_active' => false])->save();

    $this->get(route('dashboard'))
        ->assertRedirect(route('center.select'))
        ->assertSessionHas('status', __('center.active_center_cleared'));

    expect($owner->fresh()->preferred_center_id)->toBeNull();
});
