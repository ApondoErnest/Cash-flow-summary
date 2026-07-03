<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\AuditLogging\Livewire\AuditLogList;
use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\Centers\Livewire\CenterSwitcher;
use App\Modules\Centers\Livewire\ManageCenterForm;
use App\Modules\Centers\Livewire\ManageCenters;
use App\Modules\Centers\Livewire\OperatingCalendar;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\CsvImports\Livewire\ImportDetail;
use App\Modules\CsvImports\Livewire\ImportList;
use App\Modules\CsvImports\Livewire\ImportResultPage;
use App\Modules\CsvImports\Livewire\RecordsExplorer;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use App\Modules\CsvVerification\Livewire\CsvVerificationCard;
use App\Modules\CsvVerification\Livewire\ImportCsv;
use App\Modules\DailyVersions\Livewire\DailyVersionList;
use App\Modules\DailyVersions\Livewire\RevisionApproval;
use App\Modules\DailyVersions\Models\DailyVersion;
use App\Modules\Dashboards\Livewire\Dashboard;
use App\Modules\Reports\Livewire\AnomalyList;
use App\Modules\Reports\Livewire\CenterReport;
use App\Modules\Reports\Models\Anomaly;
use App\Modules\Reports\Models\ExportRequest;
use App\Modules\Settings\Livewire\OrganizationSettings;
use App\Modules\Settings\Livewire\SecuritySettings;
use App\Modules\Settings\Livewire\WhatsappSettings;
use App\Modules\Users\Livewire\ManageUserForm;
use App\Modules\Users\Livewire\ManageUsers;
use App\Modules\WhatsApp\Livewire\WhatsappHistoryPage;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Policies\AnomalyPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\CenterPolicy;
use App\Policies\DailyVersionPolicy;
use App\Policies\ImportPolicy;
use App\Policies\MasterCashFlowRecordPolicy;
use App\Policies\ReportExportPolicy;
use App\Policies\UserPolicy;
use App\Policies\WhatsappMessagePolicy;
use App\Support\Auth\PasswordRules;
use App\Support\Auth\RoleName;
use App\Support\Center\JobCenterContextService;
use App\Support\Navigation\RoleNavigation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JobCenterContextService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => PasswordRules::rule());

        Gate::policy(Center::class, CenterPolicy::class);
        Gate::policy(Import::class, ImportPolicy::class);
        Gate::policy(MasterCashFlowRecord::class, MasterCashFlowRecordPolicy::class);
        Gate::policy(DailyVersion::class, DailyVersionPolicy::class);
        Gate::policy(Anomaly::class, AnomalyPolicy::class);
        Gate::policy(ExportRequest::class, ReportExportPolicy::class);
        Gate::policy(WhatsappMessage::class, WhatsappMessagePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);

        Livewire::component('dashboards.dashboard', Dashboard::class);
        Livewire::component('daily-versions.daily-version-list', DailyVersionList::class);
        Livewire::component('daily-versions.revision-approval', RevisionApproval::class);
        Livewire::component('reports.anomaly-list', AnomalyList::class);
        Livewire::component('reports.center-report', CenterReport::class);
        Livewire::component('whatsapp.whatsapp-history', WhatsappHistoryPage::class);
        Livewire::component('csv-imports.records-explorer', RecordsExplorer::class);
        Livewire::component('csv-imports.import-list', ImportList::class);
        Livewire::component('csv-imports.import-detail', ImportDetail::class);
        Livewire::component('csv-imports.import-result', ImportResultPage::class);
        Livewire::component('csv-verification.csv-verification-card', CsvVerificationCard::class);
        Livewire::component('csv-verification.import-csv', ImportCsv::class);
        Livewire::component('centers.center-switcher', CenterSwitcher::class);
        Livewire::component('centers.manage-centers', ManageCenters::class);
        Livewire::component('centers.manage-center-form', ManageCenterForm::class);
        Livewire::component('centers.operating-calendar', OperatingCalendar::class);
        Livewire::component('users.manage-users', ManageUsers::class);
        Livewire::component('users.manage-user-form', ManageUserForm::class);
        Livewire::component('settings.organization-settings', OrganizationSettings::class);
        Livewire::component('settings.whatsapp-settings', WhatsappSettings::class);
        Livewire::component('settings.security-settings', SecuritySettings::class);
        Livewire::component('audit-logging.audit-log-list', AuditLogList::class);

        View::composer('components.layouts.shell', function ($view): void {
            $user = auth()->user();

            $role = match (true) {
                $user?->hasRole(RoleName::CenterManager) === true => UserRole::Manager,
                $user?->hasRole(RoleName::Cashier) === true => UserRole::Cashier,
                $user?->isOwner() === true => UserRole::Owner,
                default => UserRole::fromPreview(
                    is_string(request()->query('role')) && request()->query('role') !== ''
                        ? (string) request()->query('role')
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
