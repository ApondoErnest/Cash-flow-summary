<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Support\Navigation\RoleNavigation;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('components.layouts.shell', function ($view): void {
            $role = UserRole::fromPreview(
                request()->query('role', config('navigation.preview_role', 'owner'))
            );

            $view->with('shell', RoleNavigation::shellContext($role));
        });
    }
}

