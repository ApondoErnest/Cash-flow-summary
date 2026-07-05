<x-ui.page wide class="mf-whatsapp-history">
    <header class="space-y-4">
        <div class="space-y-2">
            <flux:heading size="xl" class="font-display text-text-heading!">
                {{ __('whatsapp.history.title') }}
            </flux:heading>
            <flux:text class="text-text-muted!">
                {{ __('whatsapp.history.description') }}
            </flux:text>
        </div>

        <div class="mf-whatsapp-history-center rounded-lg border border-slate-200/80 bg-white/70 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('whatsapp.history.center_label') }}</p>
            <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->centerName }}</p>
        </div>
    </header>

    @if ($this->selectedMessage)
        <x-ui.card compact :title="__('whatsapp.history.detail_title')" class="mf-whatsapp-history-detail">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('whatsapp.history.columns.event') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedMessage->eventTypeLabel }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('whatsapp.history.columns.status') }}</p>
                        <div class="mt-1">
                            <x-ui.status-badge :status="$this->selectedMessage->statusVariant">{{ $this->selectedMessage->statusLabel }}</x-ui.status-badge>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('whatsapp.history.detail.recipient') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedMessage->recipientPhone }}</p>
                    </div>
                    @if ($this->selectedMessage->templateName)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('whatsapp.history.detail.template') }}</p>
                            <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedMessage->templateName }}</p>
                        </div>
                    @endif
                    @if ($this->selectedMessage->providerMessageId)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('whatsapp.history.detail.provider_id') }}</p>
                            <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedMessage->providerMessageId }}</p>
                        </div>
                    @endif
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('whatsapp.history.detail.retries') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedMessage->retryCount }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('whatsapp.history.detail.created_at') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedMessage->createdAt }}</p>
                    </div>
                    @if ($this->selectedMessage->sentAt)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('whatsapp.history.detail.sent_at') }}</p>
                            <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedMessage->sentAt }}</p>
                        </div>
                    @endif
                    @if ($this->selectedMessage->deliveredAt)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('whatsapp.history.detail.delivered_at') }}</p>
                            <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedMessage->deliveredAt }}</p>
                        </div>
                    @endif
                    @if ($this->selectedMessage->readAt)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('whatsapp.history.detail.read_at') }}</p>
                            <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedMessage->readAt }}</p>
                        </div>
                    @endif
                    @if ($this->selectedMessage->errorReason)
                        <div class="sm:col-span-2 lg:col-span-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('whatsapp.history.detail.error') }}</p>
                            <p class="mt-1 text-sm text-error">{{ $this->selectedMessage->errorReason }}</p>
                        </div>
                    @endif
                </div>

                <flux:button variant="ghost" size="sm" wire:click="clearSelection" class="shrink-0">
                    {{ __('whatsapp.history.close_detail') }}
                </flux:button>
            </div>

            @if ($this->selectedMessage->canResend)
                <div class="mt-4 flex justify-end border-t border-slate-200/80 pt-4">
                    <flux:button variant="primary" size="sm" wire:click="resendMessage">
                        {{ __('whatsapp.history.resend') }}
                    </flux:button>
                </div>
            @endif

            @if ($this->selectedMessage->payloadRows !== [])
                <div class="mt-4 border-t border-slate-200/80 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('whatsapp.history.payload_title') }}</p>
                    <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($this->selectedMessage->payloadRows as $row)
                            <div>
                                <dt class="text-xs text-text-muted">{{ $row['label'] }}</dt>
                                <dd class="mt-1 text-sm font-medium text-text-heading">{{ $row['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif

            @if ($this->selectedMessage->importId !== null)
                <div class="mt-4 border-t border-slate-200/80 pt-4">
                    <flux:button
                        variant="ghost"
                        size="sm"
                        :href="route('imports.show', $this->selectedMessage->importId)"
                        wire:navigate
                    >
                        {{ __('whatsapp.history.view_import', ['filename' => $this->selectedMessage->importFilename ?? '']) }}
                    </flux:button>
                </div>
            @endif
        </x-ui.card>
    @endif

    <x-ui.table-panel
        :title="__('whatsapp.history.table_title')"
        :description="__('whatsapp.history.table_description')"
    >
        <x-slot:filters>
            <x-ui.filter-bar>
                <x-ui.filter-field :label="__('ui.filter.status')" :span="3">
                    <flux:select wire:model.live="statusFilter" class="w-full">
                        <flux:select.option value="">{{ __('whatsapp.history.filters.all_statuses') }}</flux:select.option>
                        @foreach ($this->statusOptions as $status)
                            @php($badge = \App\Modules\WhatsApp\Support\WhatsappMessagePresenter::statusBadge($status))
                            <flux:select.option value="{{ $status->value }}">{{ $badge['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.event')" :span="3">
                    <flux:select wire:model.live="eventTypeFilter" class="w-full">
                        <flux:select.option value="">{{ __('whatsapp.history.filters.all_events') }}</flux:select.option>
                        @foreach ($this->eventTypeOptions as $eventType)
                            <flux:select.option value="{{ $eventType }}">{{ \App\Modules\WhatsApp\Support\WhatsappMessagePresenter::eventTypeLabel($eventType) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.from')" :span="3">
                    <x-ui.date-picker wire:model.live="fromDate" />
                </x-ui.filter-field>

                <x-ui.filter-field :label="__('ui.filter.to')" :span="3">
                    <x-ui.date-picker wire:model.live="toDate" />
                </x-ui.filter-field>
            </x-ui.filter-bar>
        </x-slot:filters>

        @if ($this->rows === [])
            <flux:text class="text-text-muted!">{{ __('whatsapp.history.empty') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('whatsapp.history.columns.event') }}</flux:table.column>
                    <flux:table.column>{{ __('whatsapp.history.columns.status') }}</flux:table.column>
                    <flux:table.column>{{ __('whatsapp.history.columns.recipient') }}</flux:table.column>
                    <flux:table.column>{{ __('whatsapp.history.columns.sent_at') }}</flux:table.column>
                    <flux:table.column>{{ __('whatsapp.history.columns.import') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('whatsapp.history.columns.actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->rows as $row)
                        <flux:table.row wire:key="whatsapp-row-{{ $row->id }}">
                            <flux:table.cell>{{ $row->eventTypeLabel }}</flux:table.cell>
                            <flux:table.cell>
                                <x-ui.status-badge :status="$row->statusVariant">{{ $row->statusLabel }}</x-ui.status-badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $row->recipientPhone }}</flux:table.cell>
                            <flux:table.cell>{{ $row->sentAt }}</flux:table.cell>
                            <flux:table.cell>{{ $row->importFilename ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" wire:click="selectMessage({{ $row->id }})">
                                    {{ __('whatsapp.history.view_detail') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="mt-4">
                {{ $this->messages->links() }}
            </div>
        @endif
    </x-ui.table-panel>
</x-ui.page>
