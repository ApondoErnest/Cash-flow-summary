<?php

declare(strict_types=1);

use App\Modules\Settings\Livewire\OrganizationSettings;
use App\Modules\Settings\Livewire\SecuritySettings;
use App\Modules\Settings\Livewire\WhatsappSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('owner can access organization settings without an active center', function () {
    $owner = actingAsOwnerWithoutActiveCenter();

    $this->get(route('settings.organization'))
        ->assertOk()
        ->assertSee(__('settings.organization.title'), false)
        ->assertSee($owner->organization->name, false)
        ->assertSee(__('settings.shell.notice'), false);
});

test('owner can access whatsapp settings without an active center', function () {
    actingAsOwnerWithoutActiveCenter();

    $this->get(route('settings.whatsapp'))
        ->assertOk()
        ->assertSee(__('settings.whatsapp.title'), false)
        ->assertSee(__('settings.whatsapp.fields.owner_phone'), false)
        ->assertSee(__('settings.whatsapp.deployment_notice'), false);
});

test('owner can access security settings without an active center', function () {
    actingAsOwnerWithoutActiveCenter();

    $this->get(route('security.index'))
        ->assertOk()
        ->assertSee(__('settings.security.title'), false)
        ->assertSee(__('settings.security.password_policy_title'), false)
        ->assertSee(__('settings.security.setup_two_factor'), false);
});

test('organization settings livewire shows organization profile fields', function () {
    $owner = actingAsOwnerWithoutActiveCenter();

    Livewire::test(OrganizationSettings::class)
        ->assertSee($owner->organization->name, false)
        ->assertSee($owner->organization->code, false)
        ->assertSee($owner->organization->currency, false);
});

test('security settings reflects configured password policy and session timeout', function () {
    actingAsOwnerWithoutActiveCenter();

    config([
        'auth_security.password.min_length' => 14,
        'auth_security.session.timeout_minutes' => 90,
    ]);

    Livewire::test(SecuritySettings::class)
        ->assertSee(__('settings.security.password_rules.min_length', ['count' => 14]), false)
        ->assertSee('90', false);
});

test('security settings shows two-factor disabled state for owner without 2fa', function () {
    actingAsOwnerWithoutActiveCenter();

    Livewire::test(SecuritySettings::class)
        ->assertSee(__('settings.security.two_factor_disabled'), false)
        ->assertSee(__('settings.security.setup_two_factor'), false);
});

test('whatsapp settings shell shows api credential fields', function () {
    actingAsOwnerWithoutActiveCenter();

    Livewire::test(WhatsappSettings::class)
        ->assertSee(__('settings.whatsapp.fields.phone_number_id'), false)
        ->assertSee(__('settings.whatsapp.fields.access_token'), false)
        ->assertSee(__('settings.whatsapp.fields.webhook_verify_token'), false);
});

test('staff cannot access settings shell pages', function () {
    actingAsManager();

    $this->get(route('settings.organization'))->assertForbidden();
    $this->get(route('settings.whatsapp'))->assertForbidden();
    $this->get(route('security.index'))->assertForbidden();
});

test('cashier cannot access settings shell pages', function () {
    actingAsCashier();

    $this->get(route('settings.organization'))->assertForbidden();
    $this->get(route('settings.whatsapp'))->assertForbidden();
    $this->get(route('security.index'))->assertForbidden();
});
