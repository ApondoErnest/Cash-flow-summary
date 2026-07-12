<?php

declare(strict_types=1);

use App\Support\Locale\AppLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('supported locales are english and french', function () {
    expect(AppLocale::supported())->toBe(['en', 'fr']);
});

test('locale can be set in session and applied', function () {
    AppLocale::set('fr');

    expect(session(config('locale.session_key')))->toBe('fr');
    expect(app()->getLocale())->toBe('fr');
});

test('login page renders french when locale session is french', function () {
    AppLocale::set('fr');

    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee('Connexion', false);
    $response->assertSee('Mot de passe', false);
    $response->assertSee('Langue', false);
});

test('login page renders english by default', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee('Sign in', false);
    $response->assertSee('Username', false);
});

test('language switcher updates locale and redirects to refresh the page', function () {
    actingAsOwner();

    Livewire::withHeaders(['Referer' => url('/')])
        ->test(\App\Livewire\LanguageSwitcher::class)
        ->call('switch', 'fr')
        ->assertRedirect(url('/'));

    expect(session(config('locale.session_key')))->toBe('fr');
});

test('language switcher does not redirect when locale is unchanged', function () {
    actingAsOwner();
    AppLocale::set('en');

    Livewire::test(\App\Livewire\LanguageSwitcher::class)
        ->call('switch', 'en')
        ->assertNoRedirect();
});

test('dashboard renders french navigation when locale is french', function () {
    actingAsOwner();

    AppLocale::set('fr');

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Opérations', false);
    $response->assertSee('Gérer les centres', false);
    $response->assertSee('Tableau de bord', false);
    $response->assertSee('data-mf-language-switcher', false);
});

test('login persists selected locale after authentication', function () {
    $this->seed(\Database\Seeders\DatabaseSeeder::class);

    Livewire::test(\App\Modules\Authentication\Livewire\Login::class)
        ->set('locale', 'fr')
        ->set('username', env('SEED_OWNER_USERNAME', 'owner'))
        ->set('password', env('SEED_OWNER_PASSWORD', 'password'))
        ->call('authenticate')
        ->assertRedirect(route('password.change'));

    expect(session(config('locale.session_key')))->toBe('fr');
});

test('html lang and carbon locale follow french ui preference', function () {
    AppLocale::set('fr');

    expect(AppLocale::htmlLang())->toBe('fr-CM')
        ->and(\Illuminate\Support\Carbon::getLocale())->toStartWith('fr');

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('lang="fr-CM"', false);
});

test('center form exposes french time picker copy when locale is french', function () {
    actingAsOwnerWithoutActiveCenter();
    AppLocale::set('fr');

    $this->get(route('centers.create'))
        ->assertOk()
        ->assertSee('Heure du résumé WhatsApp', false)
        ->assertSee('Choisir une heure', false)
        ->assertSee('Format 24 heures', false)
        ->assertSee('use12Hour: false', false)
        ->assertDontSee('type="time"', false);
});

test('center form exposes english 12-hour time picker when locale is english', function () {
    actingAsOwnerWithoutActiveCenter();
    AppLocale::set('en');

    $this->get(route('centers.create'))
        ->assertOk()
        ->assertSee('WhatsApp summary time', false)
        ->assertSee('12-hour format', false)
        ->assertSee('use12Hour: true', false)
        ->assertSee('AM', false)
        ->assertSee('PM', false);
});
