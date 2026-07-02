<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Livewire;

use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\DailyVersions\Models\DailyVersion;
use App\Modules\DailyVersions\Services\RevisionQueueService;
use App\Modules\DailyVersions\Services\RevisionService;
use App\Modules\DailyVersions\Support\RevisionQueueRow;
use App\Support\Center\CenterContextResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class RevisionApproval extends Component
{
    #[Url(as: 'revision', history: true)]
    public ?int $selectedRevisionId = null;

    public string $rejectReason = '';

    public function selectRevision(int $versionId): void
    {
        $version = DailyVersion::query()->withoutCenterScope()->find($versionId);
        $user = auth()->user();

        if ($version === null
            || $user === null
            || ! app(CenterContextResolver::class)->resourceBelongsToResolvedCenter($user, $version)) {
            $this->selectedRevisionId = null;
            $this->rejectReason = '';

            return;
        }

        $this->selectedRevisionId = $version->id;
        $this->rejectReason = '';
        $this->resetErrorBag();
    }

    public function clearSelection(): void
    {
        $this->selectedRevisionId = null;
        $this->rejectReason = '';
        $this->resetErrorBag();
    }

    public function approve(RevisionService $revisionService): void
    {
        if (! auth()->user()?->isOwner()) {
            return;
        }

        $version = $this->resolveSelectedRevision();

        if ($version === null) {
            return;
        }

        try {
            $revisionService->approve(auth()->user(), $version);
        } catch (AuthorizationException|InvalidArgumentException $exception) {
            $this->addError('approve', $exception->getMessage());

            return;
        }

        $this->clearSelection();
        session()->flash('status', __('daily_versions.revisions.approve_success'));
    }

    public function reject(RevisionService $revisionService): void
    {
        if (! auth()->user()?->isOwner()) {
            return;
        }

        $this->validate([
            'rejectReason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [], [
            'rejectReason' => __('daily_versions.revisions.reject_reason_label'),
        ]);

        $version = $this->resolveSelectedRevision();

        if ($version === null) {
            return;
        }

        try {
            $revisionService->reject(auth()->user(), $version, $this->rejectReason);
        } catch (AuthorizationException|InvalidArgumentException $exception) {
            $this->addError('reject', $exception->getMessage());

            return;
        }

        $this->clearSelection();
        session()->flash('status', __('daily_versions.revisions.reject_success'));
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

    /**
     * @return list<RevisionQueueRow>
     */
    #[Computed]
    public function pendingRevisions(): array
    {
        $service = app(RevisionQueueService::class);

        return $service->pendingForActiveCenter()
            ->map(static fn (DailyVersion $version): RevisionQueueRow => $service->toQueueRow($version))
            ->all();
    }

    #[Computed]
    public function selectedRevision(): ?RevisionQueueRow
    {
        if ($this->selectedRevisionId === null) {
            return null;
        }

        $version = app(RevisionQueueService::class)->findPending($this->selectedRevisionId);

        if ($version === null) {
            return null;
        }

        return app(RevisionQueueService::class)->toQueueRow($version);
    }

    #[Computed]
    public function canApprove(): bool
    {
        return auth()->user()?->isOwner() === true;
    }

    private function resolveSelectedRevision(): ?DailyVersion
    {
        if ($this->selectedRevisionId === null) {
            return null;
        }

        return app(RevisionQueueService::class)->findPending($this->selectedRevisionId);
    }

    public function render(): View
    {
        return view('livewire.daily-versions.revision-approval')
            ->title(__('daily_versions.revisions.page_title'));
    }
}
