<div
    @if ($this->cardPhase() === \App\Modules\CsvVerification\Enums\CsvVerificationCardPhase::Verifying)
        wire:poll.3s="refreshVerification"
    @endif
>
<x-ui.card
    @class([
        'mf-csv-verification-card',
        'mf-csv-verification-card--compact' => $this->isCompactLayout,
    ])
    :compact="$this->isCompactLayout"
    :title="$this->isCompactLayout ? null : __('csv_verification.card.heading')"
    data-mf-csv-verification-card
>
    <div @class([
        'space-y-4' => $this->isCompactLayout,
        'space-y-6' => ! $this->isCompactLayout,
    ])>
        @unless ($this->isCompactLayout)
            <div class="mf-csv-verification-center">
                <flux:text class="text-xs font-semibold uppercase tracking-wide text-text-muted!">
                    {{ $this->centerLabel }}
                </flux:text>
                <div class="mt-1 flex items-center gap-2">
                    <span class="mf-csv-verification-center-icon" aria-hidden="true">
                        <flux:icon icon="building-office-2" variant="outline" class="size-4 text-emerald-brand" />
                    </span>
                    <flux:heading size="md" class="font-display text-text-heading!">
                        {{ $this->centerName }}
                    </flux:heading>
                </div>
            </div>

            <flux:callout variant="info" icon="information-circle" class="text-sm">
                {{ __('csv_verification.card.format_note') }}
            </flux:callout>
        @else
            <flux:text class="text-xs text-text-muted!">
                {{ __('csv_verification.card.format_note') }}
            </flux:text>
        @endunless

        @if ($this->cardPhase() === \App\Modules\CsvVerification\Enums\CsvVerificationCardPhase::Empty || $this->cardPhase() === \App\Modules\CsvVerification\Enums\CsvVerificationCardPhase::FileSelected)
            <div @class([
                'space-y-3' => $this->isCompactLayout,
                'space-y-5' => ! $this->isCompactLayout,
            ])>
                <flux:field>
                    <flux:label>{{ __('csv_verification.card.import_mode_label') }}</flux:label>
                    <flux:select
                        wire:model.live="importMode"
                        :disabled="$this->cardPhase() === \App\Modules\CsvVerification\Enums\CsvVerificationCardPhase::Verifying"
                    >
                        @foreach ($this->importModes as $mode)
                            <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:description>
                        {{ \App\Modules\CsvVerification\Enums\ImportMode::from($importMode)->description() }}
                    </flux:description>
                </flux:field>

                @if ($importMode === \App\Modules\CsvVerification\Enums\ImportMode::Historical->value)
                    <flux:checkbox wire:model.live="notifyOwner">
                        {{ __('csv_verification.card.notify_owner_label') }}
                    </flux:checkbox>
                    <flux:text class="-mt-2 text-sm text-text-muted!">
                        {{ __('csv_verification.card.notify_owner_help') }}
                    </flux:text>
                @endif

                @if ($this->isCorrectionMode)
                    <flux:callout variant="warning" icon="exclamation-triangle" class="text-sm">
                        {{ $this->isStaffView
                            ? __('csv_verification.correction.manager_notice')
                            : __('csv_verification.correction.owner_notice') }}
                    </flux:callout>
                @endif

                <div class="space-y-2">
                    <flux:label>{{ __('csv_verification.card.file_label') }}</flux:label>

                    @if ($this->cardPhase() === \App\Modules\CsvVerification\Enums\CsvVerificationCardPhase::FileSelected)
                        <div class="mf-csv-verification-file-selected">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-text-heading">{{ $csvFile->getClientOriginalName() }}</p>
                                <p class="mt-1 text-sm text-text-muted">
                                    {{ __('csv_verification.card.file_selected', [
                                        'filename' => $csvFile->getClientOriginalName(),
                                        'size' => $this->formatBytes((int) $csvFile->getSize()),
                                    ]) }}
                                </p>
                            </div>
                            <flux:button variant="ghost" size="sm" wire:click="removeFile">
                                {{ __('csv_verification.card.remove_file') }}
                            </flux:button>
                        </div>
                    @else
                        <label class="mf-csv-verification-dropzone">
                            <input
                                type="file"
                                wire:model="csvFile"
                                accept=".csv,text/csv"
                                class="sr-only"
                            />
                            <flux:icon icon="arrow-up-tray" variant="outline" class="mx-auto size-6 text-emerald-brand" />
                            <span class="mt-2 block font-medium text-text-heading">
                                {{ __('csv_verification.card.file_drop_title') }}
                            </span>
                            <span class="mt-0.5 block text-sm text-text-muted">
                                {{ __('csv_verification.card.file_hint') }}
                            </span>
                        </label>
                    @endif

                    @error('csvFile')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror

                    <div wire:loading wire:target="csvFile" class="text-sm text-text-muted">
                        {{ __('csv_verification.card.uploading') }}
                    </div>
                </div>

                <div @class([
                    'flex flex-col border-t border-slate-200/80 sm:flex-row sm:items-center sm:justify-between',
                    'gap-2 pt-3' => $this->isCompactLayout,
                    'gap-3 pt-5' => ! $this->isCompactLayout,
                ])>
                    @unless ($this->isCompactLayout)
                        <flux:text class="text-sm text-text-muted!">
                            {{ __('csv_verification.card.verify_help') }}
                        </flux:text>
                    @endunless

                    <x-ui.button
                        variant="primary"
                        icon="shield-check"
                        wire:click="verify"
                        wire:loading.attr="disabled"
                        wire:target="verify,csvFile"
                        :disabled="$this->cardPhase() !== \App\Modules\CsvVerification\Enums\CsvVerificationCardPhase::FileSelected"
                        @class(['w-full sm:w-auto' => $this->isCompactLayout])
                    >
                        <span wire:loading.remove wire:target="verify">{{ __('csv_verification.card.verify') }}</span>
                        <span wire:loading wire:target="verify">{{ __('csv_verification.card.verifying') }}</span>
                    </x-ui.button>
                </div>

                @if ($this->isCompactLayout)
                    <flux:text class="text-xs text-text-muted!">
                        {{ __('csv_verification.card.verify_help') }}
                    </flux:text>
                @endif
            </div>
        @endif

        @if ($this->cardPhase() === \App\Modules\CsvVerification\Enums\CsvVerificationCardPhase::Verifying)
            @php($verification = $this->verification)
            <div class="mf-csv-verification-status mf-csv-verification-status--verifying">
                <div class="mf-csv-verification-spinner" aria-hidden="true"></div>
                <div class="min-w-0 space-y-1">
                    <flux:heading size="md" class="font-display text-text-heading!">
                        {{ __('csv_verification.card.verifying_title') }}
                    </flux:heading>
                    <flux:text class="text-text-muted!">
                        {{ __('csv_verification.card.verifying_message', ['filename' => $verification?->original_filename ?? '']) }}
                    </flux:text>
                </div>
            </div>
        @endif

        @if ($this->cardPhase() === \App\Modules\CsvVerification\Enums\CsvVerificationCardPhase::Ready || $this->cardPhase() === \App\Modules\CsvVerification\Enums\CsvVerificationCardPhase::Importing)
            @include('livewire.csv-verification.verification-summary')
        @endif

        @if ($this->cardPhase() === \App\Modules\CsvVerification\Enums\CsvVerificationCardPhase::Invalid)
            @php($verification = $this->verification)
            <flux:callout variant="danger" icon="x-circle" class="text-sm">
                <div class="space-y-1">
                    <p class="font-medium">{{ __('csv_verification.card.failed_title') }}</p>
                    <p>{{ $verification?->error_message ?? __('csv_verification.card.failed_message') }}</p>
                </div>
            </flux:callout>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                @if ($this->canDownloadErrorReport)
                    <flux:button
                        variant="outline"
                        icon="arrow-down-tray"
                        :href="$this->errorReportDownloadUrl"
                        class="mf-btn-secondary"
                    >
                        {{ __('csv_verification.summary.download_errors') }}
                    </flux:button>
                @endif
                <flux:button variant="ghost" wire:click="removeFile">
                    {{ __('csv_verification.card.remove_and_retry') }}
                </flux:button>
            </div>
        @endif
    </div>
</x-ui.card>
</div>
