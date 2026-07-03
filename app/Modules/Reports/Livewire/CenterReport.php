<?php

declare(strict_types=1);

namespace App\Modules\Reports\Livewire;

use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\Dashboards\Enums\DashboardPeriod;
use App\Modules\Reports\Enums\ExportFormat;
use App\Modules\Reports\Services\ExportService;
use App\Modules\Reports\Services\ReportQueryService;
use App\Modules\Reports\Support\CenterReportData;
use App\Support\Auth\RoleName;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class CenterReport extends Component
{
    #[Url(as: 'period', history: true)]
    public string $period = 'month';

    #[Url(as: 'from', history: true)]
    public ?string $fromDate = null;

    #[Url(as: 'to', history: true)]
    public ?string $toDate = null;

    public bool $showCustomPeriodModal = false;

    public string $customFromDate = '';

    public string $customToDate = '';

    public string $periodBeforeCustom = 'month';

    public function mount(ActiveCenterContextService $activeCenterContextService): void
    {
        $user = auth()->user();

        if ($user === null) {
            abort(403);
        }

        if ($user->isOwner() !== true && $user->hasRole(RoleName::CenterManager) !== true) {
            abort(403);
        }

        if ($user->isOwner() === true && $activeCenterContextService->resolve($user) === null) {
            $this->redirect(route('center.select'), navigate: true);
        }

        if ($this->period === DashboardPeriod::Custom->value && ! $this->hasValidCustomRange()) {
            $this->period = DashboardPeriod::Month->value;
            $this->fromDate = null;
            $this->toDate = null;
        }

        if ($this->hasValidCustomRange()) {
            $this->customFromDate = $this->fromDate ?? '';
            $this->customToDate = $this->toDate ?? '';
        }
    }

    public function updatedPeriod(string $value): void
    {
        if ($value === DashboardPeriod::Custom->value) {
            $this->openCustomPeriodModal();

            return;
        }

        $this->fromDate = null;
        $this->toDate = null;
        $this->showCustomPeriodModal = false;
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
    }

    public function requestExport(string $format): void
    {
        $exportFormat = ExportFormat::tryFrom($format);

        if ($exportFormat === null) {
            return;
        }

        $period = DashboardPeriod::tryFrom($this->period) ?? DashboardPeriod::Month;

        if ($period === DashboardPeriod::Custom && ! $this->hasValidCustomRange()) {
            session()->flash('error', __('reports.export.invalid_period'));

            return;
        }

        $user = auth()->user();

        if ($user === null) {
            abort(403);
        }

        $customFrom = null;
        $customTo = null;

        if ($period === DashboardPeriod::Custom) {
            $customFrom = Carbon::parse($this->fromDate)->startOfDay();
            $customTo = Carbon::parse($this->toDate)->endOfDay();
        }

        app(ExportService::class)->requestCenterReportExport(
            user: $user,
            center: $this->resolvedCenter(),
            format: $exportFormat,
            period: $period,
            customFrom: $customFrom,
            customTo: $customTo,
        );

        session()->flash('status', __('reports.export.queued', [
            'format' => __('reports.export.formats.'.$exportFormat->value),
        ]));
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
    public function centerName(): string
    {
        $user = auth()->user();
        $context = app(ActiveCenterContextService::class)->resolve($user);

        if ($context !== null) {
            return $context->centerName;
        }

        return $user?->center?->name ?? '—';
    }

    #[Computed]
    public function isManagerView(): bool
    {
        return auth()->user()?->hasRole(RoleName::CenterManager) === true;
    }

    #[Computed]
    public function pageDescription(): string
    {
        if ($this->isManagerView) {
            return __('reports.page.manager.subtitle', ['center' => $this->centerName]);
        }

        return __('reports.description');
    }

    #[Computed]
    public function centerBannerLabel(): string
    {
        if ($this->isManagerView) {
            return __('reports.page.manager.center_label');
        }

        return __('reports.center_label');
    }

    #[Computed]
    public function recentExports()
    {
        $user = auth()->user();

        if ($user === null) {
            return collect();
        }

        return app(ExportService::class)->recentExportsForCenter(
            user: $user,
            center: $this->resolvedCenter(),
        );
    }

    #[Computed]
    public function hasPendingExports(): bool
    {
        return $this->recentExports->contains(fn ($row): bool => $row->isInProgress);
    }

    #[Computed]
    public function report(): CenterReportData
    {
        $period = DashboardPeriod::tryFrom($this->period) ?? DashboardPeriod::Month;
        $customFrom = null;
        $customTo = null;

        if ($period === DashboardPeriod::Custom) {
            $customFrom = Carbon::parse($this->fromDate)->startOfDay();
            $customTo = Carbon::parse($this->toDate)->endOfDay();
        }

        return app(ReportQueryService::class)->buildCenterReport(
            center: $this->resolvedCenter(),
            period: $period,
            customFrom: $customFrom,
            customTo: $customTo,
        );
    }

    public function render(): View
    {
        $report = $this->report;

        return view('livewire.reports.center-report', [
            'report' => $report,
            'periods' => DashboardPeriod::filterOptions(),
            'exportFormats' => ExportFormat::cases(),
        ])->title(__('reports.page_title'));
    }

    private function resolvedCenter(): Center
    {
        $user = auth()->user();

        if ($user?->hasRole(RoleName::CenterManager) === true) {
            $center = $user->center;

            if ($center === null) {
                abort(403);
            }

            return $center;
        }

        $context = app(ActiveCenterContextService::class)->resolve($user);

        return Center::query()->findOrFail($context?->centerId);
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
}
