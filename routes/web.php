<?php

use App\Modules\Authentication\Http\Controllers\LogoutController;
use App\Modules\Authentication\Livewire\ChangePassword;
use App\Modules\Authentication\Livewire\Login;
use App\Modules\Authentication\Livewire\TwoFactorChallenge;
use App\Modules\Authentication\Livewire\TwoFactorSetup;
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
    Route::view('center/select', 'pages.placeholder', ['pageKey' => 'center.select'])->name('center.select');
});

Route::middleware(['auth', 'password-changed', 'two-factor', 'assigned-center'])->group(function (): void {
    Route::middleware('owner-active-center')->group(function (): void {
        Route::get('/', function () {
            return view('welcome');
        })->name('dashboard');

        require __DIR__.'/navigation.php';
    });
});
