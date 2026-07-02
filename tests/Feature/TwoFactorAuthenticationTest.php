<?php

declare(strict_types=1);

use App\Modules\Authentication\Livewire\Login;
use App\Modules\Authentication\Livewire\TwoFactorChallenge;
use App\Modules\Authentication\Livewire\TwoFactorSetup;
use App\Modules\Authentication\Services\TwoFactorService;
use App\Support\Auth\RoleName;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

require __DIR__.'/../Support/TwoFactorHelpers.php';

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('owner can enable two-factor authentication', function () {
    $owner = actingAsOwner();
    $twoFactorService = app(TwoFactorService::class);

    $component = Livewire::test(TwoFactorSetup::class);

    $secret = $component->get('pendingSecret');
    $code = currentTwoFactorCode($secret);

    $component
        ->set('confirmationCode', $code)
        ->call('confirm')
        ->assertSet('showRecoveryCodes', true)
        ->assertSee(__('two_factor.recovery_codes_heading'), false);

    expect($owner->fresh()->hasTwoFactorEnabled())->toBeTrue();
    expect($twoFactorService->isVerified())->toBeTrue();
});

test('owner login redirects to two-factor challenge when enabled', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    $owner->forceFill(['must_change_password' => false])->save();
    enableTwoFactorFor($owner);

    Livewire::test(Login::class)
        ->set('username', $owner->username)
        ->set('password', env('SEED_OWNER_PASSWORD', 'password'))
        ->call('authenticate')
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertAuthenticatedAs($owner);
});

test('owner can complete login with valid authenticator code', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    $owner->forceFill(['must_change_password' => false])->save();
    $secret = enableTwoFactorFor($owner);

    Livewire::test(Login::class)
        ->set('username', $owner->username)
        ->set('password', env('SEED_OWNER_PASSWORD', 'password'))
        ->call('authenticate');

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', currentTwoFactorCode($secret))
        ->call('verify')
        ->assertRedirect(route('center.select'));

    expect(app(TwoFactorService::class)->isVerified())->toBeTrue();
    $this->get(route('center.select'))->assertOk();
});

test('two-factor challenge rejects invalid codes', function () {
    $owner = actingAsOwner();
    enableTwoFactorFor($owner);
    app(TwoFactorService::class)->clearVerification();

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', '000000')
        ->call('verify')
        ->assertHasErrors(['code']);
});

test('owner can sign in using a recovery code', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    $owner->forceFill(['must_change_password' => false])->save();
    $secret = app(\PragmaRX\Google2FA\Google2FA::class)->generateSecretKey();
    $recoveryCodes = app(TwoFactorService::class)->enable($owner, $secret);

    Livewire::test(Login::class)
        ->set('username', $owner->username)
        ->set('password', env('SEED_OWNER_PASSWORD', 'password'))
        ->call('authenticate');

    Livewire::test(TwoFactorChallenge::class)
        ->set('useRecoveryCode', true)
        ->set('code', $recoveryCodes[0])
        ->call('verify')
        ->assertRedirect(route('center.select'));
});

test('non-owner cannot access two-factor setup', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();

    Role::findOrCreate(RoleName::Cashier, 'web');

    $cashier = User::query()->create([
        'organization_id' => $owner->organization_id,
        'center_id' => null,
        'name' => 'Demo Cashier',
        'username' => 'cashier-demo',
        'password' => bcrypt('password'),
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $cashier->assignRole(RoleName::Cashier);

    $this->actingAs($cashier)
        ->get(route('two-factor.setup'))
        ->assertForbidden();
});

test('dashboard is blocked until two-factor verification completes', function () {
    $owner = User::query()->where('username', env('SEED_OWNER_USERNAME', 'owner'))->firstOrFail();
    $owner->forceFill(['must_change_password' => false])->save();
    enableTwoFactorFor($owner);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertRedirect(route('two-factor.challenge'));
});
