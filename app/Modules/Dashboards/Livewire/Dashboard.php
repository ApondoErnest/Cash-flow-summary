<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Livewire;

use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\Dashboards\Enums\DashboardPeriod;
use App\Modules\Dashboards\Enums\DashboardTrendGranularity;
use App\Modules\Dashboards\Services\CashierDashboardService;
use App\Modules\Dashboards\Services\ManagerDashboardService;
use App\Modules\Dashboards\Services\OwnerDashboardService;
use App\Modules\Dashboards\Support\CashierDashboardData;
use App\Modules\Dashboards\Support\ManagerDashboardData;
use App\Modules\Dashboards\Support\OwnerDashboardData;
use App\Support\Auth\RoleName;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    #[Url(as: 'period', history: true)]
    public string $period = 'today';

    #[Url(as: 'from', history: true)]
    public ?string $fromDate = null;

    #[Url(as: 'to', history: true)]
    public ?string $toDate = null;

    #[Url(as: 'trend', history: true)]
    public string $trend = 'daily';

    public bool $showCustomPeriodModal = false;

    public string $customFromDate = '';

    public string $customToDate = '';

    public string $periodBeforeCustom = 'today';

    public function mount(ActiveCenterContextService $activeCenterContextService): void
    {
        $user = auth()->user();

        if ($user?->isOwner() !== true) {
            return;
        }

        if ($activeCenterContextService->resolve($user) === null) {
            $this->redirect(route('center.select'), navigate: true);
        }

        if ($this->period === DashboardPeriod::Custom->value && ! $this->hasValidCustomRange()) {
            $this->period = DashboardPeriod::Today->value;
            $this->fromDate = null;
            $this->toDate = null;
        }

        if ($this->hasValidCustomRange()) {
            $this->customFromDate = $this->fromDate ?? '';
            $this->customToDate = $this->toDate ?? '';
        }

        $this->persistPeriodFilter();
    }

    public function updatedPeriod(string $value): void
    {
        if (auth()->user()?->isOwner() !== true) {
            return;
        }

        if ($value === DashboardPeriod::Custom->value) {
            $this->openCustomPeriodModal();

            return;
        }

        $this->fromDate = null;
        $this->toDate = null;
        $this->showCustomPeriodModal = false;
        $this->persistPeriodFilter();
    }

    public function updatingPeriod(string &$value): void
    {
        if ($value === DashboardPeriod::Custom->value && $this->period !== DashboardPeriod::Custom->value) {
            $this->periodBeforeCustom = $this->period;
        }
    }

    public function openCustomPeriodModal(): void
    {
        if ($this->period !== DashboardPeriod::Custom->value) {
            $this->periodBeforeCustom = $this->period;
        }

        if ($this->hasValidCustomRange()) {
            $this->customFromDate = $this->fromDate ?? '';
            $this->customToDate = $this->toDate ?? '';
        } else {
            $this->customFromDate = now()->startOfMonth()->toDateString();
            $this->customToDate = now()->toDateString();
        }

        $this->showCustomPeriodModal = true;

        if (! $this->hasValidCustomRange()) {
            $this->period = $this->periodBeforeCustom;
        }
    }

    public function applyCustomPeriod(): void
    {
        $this->validate([
            'customFromDate' => ['required', 'date'],
            'customToDate' => ['required', 'date', 'after_or_equal:customFromDate'],
        ], [], [
            'customFromDate' => __('dashboard.period.fields.from'),
            'customToDate' => __('dashboard.period.fields.to'),
        ]);

        $this->fromDate = $this->customFromDate;
        $this->toDate = $this->customToDate;
        $this->period = DashboardPeriod::Custom->value;
        $this->showCustomPeriodModal = false;
        $this->persistPeriodFilter();
    }

    public function cancelCustomPeriod(): void
    {
        $this->showCustomPeriodModal = false;
        $this->resetErrorBag();

        if ($this->period === DashboardPeriod::Custom->value && $this->hasValidCustomRange()) {
            $this->customFromDate = $this->fromDate ?? '';
            $this->customToDate = $this->toDate ?? '';

            return;
        }

        if ($this->period !== DashboardPeriod::Custom->value) {
            return;
        }

        $this->period = $this->periodBeforeCustom;
    }

    #[Computed]
    public function cashierDashboard(): ?CashierDashboardData
    {
        $user = auth()->user();

        if ($user === null || ! $user->hasRole(RoleName::Cashier)) {
            return null;
        }

        $center = $user->center;

        if ($center === null) {
            return null;
        }

        return app(CashierDashboardService::class)->build($center);
    }

    #[Computed]
    public function managerDashboard(): ?ManagerDashboardData
    {
        $user = auth()->user();

        if ($user === null || ! $user->hasRole(RoleName::CenterManager)) {
            return null;
        }

        $center = $user->center;

        if ($center === null) {
            return null;
        }

        return app(ManagerDashboardService::class)->build(
            center: $center,
            trendGranularity: DashboardTrendGranularity::from($this->trend),
        );
    }

    #[Computed]
    public function ownerDashboard(): ?OwnerDashboardData
    {
        $user = auth()->user();

        if ($user?->isOwner() !== true) {
            return null;
        }

        $period = DashboardPeriod::tryFrom($this->period) ?? DashboardPeriod::Today;
        $customFrom = null;
        $customTo = null;

        if ($period === DashboardPeriod::Custom) {
            $customFrom = Carbon::parse($this->fromDate)->startOfDay();
            $customTo = Carbon::parse($this->toDate)->endOfDay();
        }

        return app(OwnerDashboardService::class)->build(
            center: $this->activeCenter(),
            period: $period,
            trendGranularity: DashboardTrendGranularity::from($this->trend),
            customFrom: $customFrom,
            customTo: $customTo,
        );
    }

    public function importStatusBadge(ImportStatus $status): array
    {
        return match ($status) {
            ImportStatus::Completed,
            ImportStatus::CompletedWithDuplicates,
            ImportStatus::CompletedWithWarnings,
            ImportStatus::ExactFileDuplicate => ['status' => 'success', 'label' => __('dashboard.import_status.completed')],
            ImportStatus::AwaitingOwnerApproval => ['status' => 'warning', 'label' => __('dashboard.import_status.revision_pending')],
            ImportStatus::Failed => ['status' => 'error', 'label' => __('dashboard.import_status.failed')],
            ImportStatus::Processing => ['status' => 'info', 'label' => __('dashboard.import_status.processing')],
            default => ['status' => 'neutral', 'label' => __('dashboard.import_status.other')],
        };
    }

    public function render(): View
    {
        $user = auth()->user();

        if ($user?->isOwner() === true) {
            $dashboard = $this->ownerDashboard;

            return view('livewire.dashboards.owner-dashboard', [
                'dashboard' => $dashboard,
                'periods' => DashboardPeriod::filterOptions(),
                'trendOptions' => DashboardTrendGranularity::cases(),
            ])->title(__('dashboard.owner.title', ['center' => $dashboard->centerName]));
        }

        if ($user?->hasRole(RoleName::CenterManager) === true) {
            $dashboard = $this->managerDashboard;

            if ($dashboard === null) {
                abort(403);
            }

            return view('livewire.dashboards.manager-dashboard', [
                'dashboard' => $dashboard,
                'trendOptions' => DashboardTrendGranularity::cases(),
            ])->title(__('dashboard.manager.title', ['center' => $dashboard->centerName]));
        }

        if ($user?->hasRole(RoleName::Cashier) === true) {
            $dashboard = $this->cashierDashboard;

            if ($dashboard === null) {
                abort(403);
            }

            return view('livewire.dashboards.cashier-dashboard', [
                'dashboard' => $dashboard,
            ])->title(__('dashboard.cashier.title', ['center' => $dashboard->centerName]));
        }

        return view('livewire.dashboards.staff-dashboard', [
            'centerName' => $user?->center?->name
                ?? app(ActiveCenterContextService::class)->resolve($user)?->centerName,
        ])->title(__('dashboard.staff.title'));
    }

    private function hasValidCustomRange(): bool
    {
        if ($this->fromDate === null || $this->toDate === null) {
            return false;
        }

        try {
            $from = Carbon::parse($this->fromDate);
            $to = Carbon::parse($this->toDate);
        } catch (\Throwable) {
            return false;
        }

        return $from->lessThanOrEqualTo($to);
    }

    private function persistPeriodFilter(): void
    {
        if (auth()->user()?->isOwner() !== true) {
            return;
        }

        session(['owner.filters.dashboard_period' => $this->period]);

        if ($this->period === DashboardPeriod::Custom->value && $this->hasValidCustomRange()) {
            session([
                'owner.filters.dashboard_period_from' => $this->fromDate,
                'owner.filters.dashboard_period_to' => $this->toDate,
            ]);

            return;
        }

        session()->forget([
            'owner.filters.dashboard_period_from',
            'owner.filters.dashboard_period_to',
        ]);
    }

    private function activeCenter(): Center
    {
        $user = auth()->user();
        $context = app(ActiveCenterContextService::class)->resolve($user);

        return Center::query()->findOrFail($context?->centerId);
    }
}
