<x-ui.page wide class="mf-revision-approval">
    <header class="space-y-4">
        <div class="space-y-2">
            <flux:heading size="xl" class="font-display text-text-heading!">
                {{ __('daily_versions.revisions.title') }}
            </flux:heading>
            <flux:text class="text-text-muted!">
                {{ __('daily_versions.revisions.description') }}
            </flux:text>
        </div>

        <div class="mf-revision-approval-center rounded-lg border border-slate-200/80 bg-white/70 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('daily_versions.revisions.center_label') }}</p>
            <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->centerName }}</p>
        </div>

        @if (session('status'))
            <flux:callout variant="success" icon="check-circle" class="text-sm">
                {{ session('status') }}
            </flux:callout>
        @endif

        @if (! $this->canApprove)
            <flux:callout variant="info" icon="information-circle" class="text-sm">
                {{ __('daily_versions.revisions.manager_notice') }}
            </flux:callout>
        @endif
    </header>

    @if ($this->selectedRevision)
        <x-ui.card compact :title="__('daily_versions.revisions.comparison_title')" class="mf-revision-comparison">
            <div class="space-y-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('daily_versions.revisions.columns.business_date') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedRevision->businessDate }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('daily_versions.revisions.columns.version') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">v{{ $this->selectedRevision->versionNumber }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ __('daily_versions.revisions.columns.submitted_by') }}</p>
                        <p class="mt-1 text-sm font-medium text-text-heading">{{ $this->selectedRevision->submittedByName }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <x-ui.card compact :title="__('daily_versions.revisions.existing_totals')" class="mf-revision-totals-card">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <x-ui.stat-card :label="__('daily_versions.list.columns.ht')" :value="$this->selectedRevision->existingHt" />
                            <x-ui.stat-card :label="__('daily_versions.list.columns.vat')" :value="$this->selectedRevision->existingVat" />
                            <x-ui.stat-card :label="__('daily_versions.list.columns.ttc')" :value="$this->selectedRevision->existingTtc" />
                        </div>
                    </x-ui.card>

                    <x-ui.card compact :title="__('daily_versions.revisions.proposed_totals')" class="mf-revision-totals-card">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <x-ui.stat-card :label="__('daily_versions.list.columns.ht')" :value="$this->selectedRevision->proposedHt" />
                            <x-ui.stat-card :label="__('daily_versions.list.columns.vat')" :value="$this->selectedRevision->proposedVat" />
                            <x-ui.stat-card :label="__('daily_versions.list.columns.ttc')" :value="$this->selectedRevision->proposedTtc" :accent="true" class="mf-revision-proposed-ttc" />
                        </div>
                    </x-ui.card>
                </div>

                @if ($this->selectedRevision->importId !== null)
                    <flux:button
                        variant="ghost"
                        size="sm"
                        :href="route('imports.show', $this->selectedRevision->importId)"
                        wire:navigate
                    >
                        {{ __('daily_versions.revisions.view_import', ['filename' => $this->selectedRevision->importFilename ?? '']) }}
                    </flux:button>
                @endif

                @if ($this->canApprove)
                    <div class="space-y-4 border-t border-slate-200/80 pt-5">
                        <flux:textarea
                            wire:model="rejectReason"
                            :label="__('daily_versions.revisions.reject_reason_label')"
                            :placeholder="__('daily_versions.revisions.reject_reason_placeholder')"
                            rows="3"
                        />

                        @error('approve')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror

                        @error('reject')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror

                        @error('rejectReason')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror

                        <div class="mf-form-actions border-t-0 pt-0 sm:justify-end">
                            <x-ui.button variant="secondary" wire:click="clearSelection">
                                {{ __('daily_versions.revisions.cancel') }}
                            </x-ui.button>

                            <x-ui.button
                                variant="secondary"
                                wire:click="reject"
                                wire:loading.attr="disabled"
                                wire:target="reject,approve"
                            >
                                {{ __('daily_versions.revisions.reject') }}
                            </x-ui.button>

                            <x-ui.button
                                variant="approval"
                                wire:click="approve"
                                wire:loading.attr="disabled"
                                wire:target="approve,reject"
                            >
                                <span wire:loading.remove wire:target="approve">{{ __('daily_versions.revisions.approve') }}</span>
                                <span wire:loading wire:target="approve">{{ __('daily_versions.revisions.approving') }}</span>
                            </x-ui.button>
                        </div>
                    </div>
                @else
                    <div class="border-t border-slate-200/80 pt-5">
                        <x-ui.button variant="secondary" wire:click="clearSelection">
                            {{ __('daily_versions.revisions.cancel') }}
                        </x-ui.button>
                    </div>
                @endif
            </div>
        </x-ui.card>
    @endif

    <x-ui.table-panel
        :title="__('daily_versions.revisions.queue_title')"
        :description="__('daily_versions.revisions.queue_description')"
    >
        @if ($this->pendingRevisions === [])
            <flux:text class="text-text-muted!">{{ __('daily_versions.revisions.empty') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('daily_versions.revisions.columns.business_date') }}</flux:table.column>
                    <flux:table.column>{{ __('daily_versions.revisions.columns.version') }}</flux:table.column>
                    <flux:table.column>{{ __('daily_versions.revisions.columns.submitted_by') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('daily_versions.revisions.columns.existing_ttc') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('daily_versions.revisions.columns.proposed_ttc') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('daily_versions.revisions.columns.actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->pendingRevisions as $revision)
                        <flux:table.row wire:key="revision-row-{{ $revision->id }}">
                            <flux:table.cell>{{ $revision->businessDate }}</flux:table.cell>
                            <flux:table.cell>v{{ $revision->versionNumber }}</flux:table.cell>
                            <flux:table.cell>{{ $revision->submittedByName }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-money">{{ $revision->existingTtc }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-money font-medium">{{ $revision->proposedTtc }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" wire:click="selectRevision({{ $revision->id }})">
                                    {{ __('daily_versions.revisions.review') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </x-ui.table-panel>
</x-ui.page>
