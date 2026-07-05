<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Livewire;

use App\Modules\AuditLogging\Services\AuditLogger;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\WhatsApp\Enums\WhatsappMessageStatus;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Modules\WhatsApp\Services\WhatsAppNotificationService;
use App\Modules\WhatsApp\Services\WhatsappHistoryService;
use App\Modules\WhatsApp\Support\WhatsappHistoryDetailData;
use App\Modules\WhatsApp\Support\WhatsappHistoryRow;
use App\Support\Center\CenterContextResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class WhatsappHistoryPage extends Component
{
    use WithPagination;

    #[Url(as: 'status', history: true)]
    public string $statusFilter = '';

    #[Url(as: 'event', history: true)]
    public string $eventTypeFilter = '';

    #[Url(as: 'from', history: true)]
    public string $fromDate = '';

    #[Url(as: 'to', history: true)]
    public string $toDate = '';

    #[Url(as: 'message', history: true)]
    public ?int $selectedMessageId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isOwner() === true, 403);
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->selectedMessageId = null;
    }

    public function updatedEventTypeFilter(): void
    {
        $this->resetPage();
        $this->selectedMessageId = null;
    }

    public function updatedFromDate(): void
    {
        $this->resetPage();
        $this->selectedMessageId = null;
    }

    public function updatedToDate(): void
    {
        $this->resetPage();
        $this->selectedMessageId = null;
    }

    public function selectMessage(int $messageId): void
    {
        $message = WhatsappMessage::query()->withoutCenterScope()->find($messageId);
        $user = auth()->user();

        if ($message === null
            || $user === null
            || ! app(CenterContextResolver::class)->resourceBelongsToResolvedCenter($user, $message)) {
            $this->selectedMessageId = null;

            return;
        }

        $this->selectedMessageId = $message->id;
    }

    public function clearSelection(): void
    {
        $this->selectedMessageId = null;
    }

    public function resendMessage(
        WhatsAppNotificationService $notificationService,
        AuditLogger $auditLogger,
    ): void {
        abort_unless(auth()->user()?->isOwner() === true, 403);

        if ($this->selectedMessageId === null) {
            return;
        }

        $message = WhatsappMessage::query()->find($this->selectedMessageId);
        $user = auth()->user();

        if ($message === null
            || $user === null
            || $message->status !== WhatsappMessageStatus::Failed
            || ! app(CenterContextResolver::class)->resourceBelongsToResolvedCenter($user, $message)) {
            return;
        }

        $resent = $notificationService->resendFailedMessage($message);

        if ($resent === null) {
            return;
        }

        $auditLogger->record(
            event: 'whatsapp.resent',
            user: $user,
            centerId: (int) $message->center_id,
            resourceType: WhatsappMessage::class,
            resourceId: (int) $message->id,
            newValues: [
                'event_type' => $message->event_type,
                'import_id' => $message->import_id,
            ],
        );

        $this->selectedMessageId = $resent->id;
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
     * @return LengthAwarePaginator<int, WhatsappMessage>
     */
    #[Computed]
    public function messages()
    {
        return app(WhatsappHistoryService::class)->paginateForActiveCenter([
            'status' => $this->statusFilter !== '' ? $this->statusFilter : null,
            'event_type' => $this->eventTypeFilter !== '' ? $this->eventTypeFilter : null,
            'from' => $this->fromDate !== '' ? $this->fromDate : null,
            'to' => $this->toDate !== '' ? $this->toDate : null,
        ]);
    }

    /**
     * @return list<WhatsappHistoryRow>
     */
    #[Computed]
    public function rows(): array
    {
        $service = app(WhatsappHistoryService::class);

        return $this->messages
            ->getCollection()
            ->map(static fn (WhatsappMessage $message): WhatsappHistoryRow => $service->toRow($message))
            ->all();
    }

    #[Computed]
    public function selectedMessage(): ?WhatsappHistoryDetailData
    {
        if ($this->selectedMessageId === null) {
            return null;
        }

        $message = WhatsappMessage::query()->find($this->selectedMessageId);

        if ($message === null) {
            return null;
        }

        return app(WhatsappHistoryService::class)->toDetail($message);
    }

    /**
     * @return list<\App\Modules\WhatsApp\Enums\WhatsappMessageStatus>
     */
    #[Computed]
    public function statusOptions()
    {
        return app(WhatsappHistoryService::class)->filterableStatuses();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function eventTypeOptions()
    {
        return app(WhatsappHistoryService::class)->filterableEventTypes();
    }

    public function render(): View
    {
        return view('livewire.whatsapp.whatsapp-history')
            ->title(__('whatsapp.history.page_title'));
    }
}
