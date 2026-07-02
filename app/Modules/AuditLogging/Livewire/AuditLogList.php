<?php

declare(strict_types=1);

namespace App\Modules\AuditLogging\Livewire;

use App\Models\User;
use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\AuditLogging\Services\AuditLogService;
use App\Modules\Centers\Models\Center;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class AuditLogList extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'center', history: true)]
    public string $centerFilter = '';

    #[Url(as: 'event', history: true)]
    public string $eventFilter = '';

    #[Url(as: 'from', history: true)]
    public string $fromDate = '';

    #[Url(as: 'to', history: true)]
    public string $toDate = '';

    public ?int $selectedLogId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', AuditLog::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->selectedLogId = null;
    }

    public function updatedCenterFilter(): void
    {
        $this->resetPage();
        $this->selectedLogId = null;
    }

    public function updatedEventFilter(): void
    {
        $this->resetPage();
        $this->selectedLogId = null;
    }

    public function updatedFromDate(): void
    {
        $this->resetPage();
        $this->selectedLogId = null;
    }

    public function updatedToDate(): void
    {
        $this->resetPage();
        $this->selectedLogId = null;
    }

    public function selectLog(int $logId): void
    {
        $log = AuditLog::query()->withoutCenterScope()->findOrFail($logId);
        $this->authorize('view', $log);
        $this->selectedLogId = $logId;
    }

    public function clearSelection(): void
    {
        $this->selectedLogId = null;
    }

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    #[Computed]
    public function logs()
    {
        $owner = auth()->user();

        if (! $owner instanceof User) {
            return AuditLog::query()->withoutCenterScope()->whereRaw('1 = 0')->paginate(25);
        }

        return app(AuditLogService::class)->listForOrganization($owner, [
            'search' => $this->search,
            'center_id' => $this->centerFilter !== '' ? (int) $this->centerFilter : null,
            'event' => $this->eventFilter !== '' ? $this->eventFilter : null,
            'from' => $this->fromDate !== '' ? $this->fromDate : null,
            'to' => $this->toDate !== '' ? $this->toDate : null,
        ]);
    }

    /**
     * @return Collection<int, Center>
     */
    #[Computed]
    public function centers()
    {
        $owner = auth()->user();

        if (! $owner instanceof User) {
            return collect();
        }

        return Center::query()
            ->where('organization_id', $owner->organization_id)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function availableEvents()
    {
        $owner = auth()->user();

        if (! $owner instanceof User) {
            return collect();
        }

        return app(AuditLogService::class)->distinctEventsForOrganization($owner);
    }

    #[Computed]
    public function selectedLog(): ?AuditLog
    {
        if ($this->selectedLogId === null) {
            return null;
        }

        $log = AuditLog::query()
            ->withoutCenterScope()
            ->with(['user:id,name,username', 'center:id,name,code'])
            ->find($this->selectedLogId);

        if ($log === null) {
            return null;
        }

        $this->authorize('view', $log);

        return $log;
    }

    public function render(AuditLogService $auditLogService): View
    {
        return view('livewire.audit-logging.audit-log-list', [
            'eventLabel' => static fn (string $event): string => $auditLogService->eventLabel($event),
            'resourceLabel' => static fn (?string $type, ?int $id): ?string => $auditLogService->resourceLabel($type, $id),
        ])->title(__('audit.list.title'));
    }
}
