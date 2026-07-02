<?php

use App\Modules\AuditLogging\Livewire\AuditLogList;
use App\Modules\Authentication\Http\Controllers\LogoutController;
use App\Modules\Authentication\Livewire\ChangePassword;
use App\Modules\Authentication\Livewire\Login;
use App\Modules\Authentication\Livewire\TwoFactorChallenge;
use App\Modules\Authentication\Livewire\TwoFactorSetup;
use App\Modules\Centers\Livewire\CenterSelection;
use App\Modules\Centers\Livewire\ManageCenterForm;
use App\Modules\Centers\Livewire\ManageCenters;
use App\Modules\Centers\Livewire\OperatingCalendar;
use App\Modules\Settings\Livewire\OrganizationSettings;
use App\Modules\Settings\Livewire\SecuritySettings;
use App\Modules\Settings\Livewire\WhatsappSettings;
use App\Modules\Users\Livewire\ManageUserForm;
use App\Modules\Users\Livewire\ManageUsers;
use App\Modules\Dashboards\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', Login::class)->name('login');
});

Route::middleware('auth')->group(function (): void {
    Route::get('password/change', ChangePassword::class)->name('password.change');
    Route::post('logout', LogoutController::class)->name('logout');
});

Route::middleware(['auth', 'password-changed'])->group(function (): void {
    Route::get('two-factor/challenge', TwoFactorChallenge::class)->name('two-factor.challenge');
    Route::get('two-factor/setup', TwoFactorSetup::class)->name('two-factor.setup');
});

Route::middleware(['auth', 'password-changed', 'two-factor', 'assigned-center', 'owner'])->group(function (): void {
    Route::get('center/select', CenterSelection::class)->name('center.select');
});

Route::middleware(['auth', 'password-changed', 'two-factor', 'assigned-center'])->group(function (): void {
    Route::middleware('owner')->group(function (): void {
        Route::get('centers', ManageCenters::class)->name('centers.index');
        Route::get('centers/create', ManageCenterForm::class)->name('centers.create');
        Route::get('centers/{center}/edit', ManageCenterForm::class)->name('centers.edit');
        Route::get('centers/{center}/calendar', OperatingCalendar::class)->name('centers.calendar');
        Route::get('users', ManageUsers::class)->name('users.index');
        Route::get('users/create', ManageUserForm::class)->name('users.create');
        Route::get('users/{user}/edit', ManageUserForm::class)->name('users.edit');
        Route::get('settings/organization', OrganizationSettings::class)->name('settings.organization');
        Route::get('settings/whatsapp', WhatsappSettings::class)->name('settings.whatsapp');
        Route::get('security', SecuritySettings::class)->name('security.index');
        Route::get('audit-logs', AuditLogList::class)->name('audit-logs.index');
    });

    Route::middleware('owner-active-center')->group(function (): void {
        Route::get('/', Dashboard::class)->name('dashboard');

        require __DIR__.'/navigation.php';
    });
});
