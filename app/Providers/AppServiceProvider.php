<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Policies\AuditLogPolicy;
use App\Policies\CenterPolicy;
use App\Support\Auth\PasswordRules;
use App\Support\Auth\RoleName;
use App\Support\Navigation\RoleNavigation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Password::defaults(fn () => PasswordRules::rule());

        Gate::policy(Center::class, CenterPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);

        View::composer('components.layouts.shell', function ($view): void {
            $user = auth()->user();
            $previewRole = request()->query('role');

            $role = match (true) {
                $user?->hasRole(RoleName::CenterManager) === true => UserRole::Manager,
                $user?->hasRole(RoleName::Cashier) === true => UserRole::Cashier,
                $user?->isOwner() === true => UserRole::fromPreview(
                    is_string($previewRole) && $previewRole !== '' ? $previewRole : 'owner'
                ),
                default => UserRole::fromPreview(
                    is_string($previewRole) && $previewRole !== ''
                        ? $previewRole
                        : config('navigation.preview_role', 'owner')
                ),
            };

            $centerName = null;

            if ($user?->isCenterStaff() && $user->center !== null) {
                $centerName = $user->center->name;
            } elseif ($user?->isOwner()) {
                $activeCenter = app(ActiveCenterContextService::class)->resolve($user);

                if ($activeCenter !== null) {
                    $centerName = $activeCenter->centerName;
                }
            }

            $view->with('shell', RoleNavigation::shellContext($role, $centerName));
        });
    }
}

