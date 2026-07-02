<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Livewire;

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Support\Auth\RoleName;
use App\Modules\CsvImports\Exceptions\ExactFileDuplicateException;
use App\Modules\CsvImports\Services\ImportService;
use App\Modules\CsvVerification\Enums\CsvVerificationCardPhase;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\CsvVerification\Services\VerificationService;
use App\Modules\CsvVerification\Services\VerificationSummaryService;
use App\Modules\CsvVerification\Support\VerificationSummaryData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class CsvVerificationCard extends Component
{
    use WithFileUploads;

    public ?UploadedFile $csvFile = null;

    public string $importMode = 'operational';

    public bool $notifyOwner = false;

    public ?string $verificationToken = null;

    public bool $isImporting = false;

    public function mount(): void
    {
        $modes = ImportMode::availableFor($this->user());

        if ($modes !== [] && ! in_array(ImportMode::tryFrom($this->importMode), $modes, true)) {
            $this->importMode = $modes[0]->value;
        }
    }

    public function updatedImportMode(): void
    {
        if ($this->importMode !== ImportMode::Historical->value) {
            $this->notifyOwner = false;
        }
    }

    public function verify(VerificationService $verificationService): void
    {
        $this->resetErrorBag();

        $this->validate([
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'importMode' => [
                'required',
                Rule::enum(ImportMode::class),
                Rule::in(array_map(
                    static fn (ImportMode $mode): string => $mode->value,
                    ImportMode::availableFor($this->user()),
                )),
            ],
            'notifyOwner' => ['boolean'],
        ], [], [
            'csvFile' => __('csv_verification.card.file_label'),
            'importMode' => __('csv_verification.card.import_mode_label'),
        ]);

        try {
            $verification = $verificationService->start(
                user: $this->user(),
                center: $this->center(),
                file: $this->csvFile,
                importMode: ImportMode::from($this->importMode),
                notifyOwner: $this->notifyOwner,
            );
        } catch (AuthorizationException $exception) {
            $this->addError('csvFile', $exception->getMessage());

            return;
        }

        $this->verificationToken = $verification->token;
        $this->csvFile = null;
    }

    public function refreshVerification(): void
    {
        if ($this->verificationToken === null) {
            return;
        }

        $verification = $this->currentVerification();

        if ($verification === null) {
            $this->verificationToken = null;

            return;
        }

        if ($verification->status === VerificationStatus::Expired) {
            $this->resetCard();
            $this->addError('csvFile', __('csv_verification.verification.expired'));
        }
    }

    public function import(ImportService $importService): void
    {
        if ($this->verificationToken === null || $this->isImporting) {
            return;
        }

        $this->resetErrorBag();
        $this->isImporting = true;

        try {
            $import = $importService->commitFromVerification($this->user(), $this->verificationToken);
        } catch (ExactFileDuplicateException $exception) {
            $this->isImporting = false;
            $this->redirect(route('imports.result', $exception->existingImport), navigate: true);

            return;
        } catch (AuthorizationException|InvalidArgumentException $exception) {
            $this->isImporting = false;
            $this->addError('import', $exception->getMessage());

            return;
        }

        $this->redirect(route('imports.result', $import), navigate: true);
    }

    public function reject(VerificationService $verificationService): void
    {
        if ($this->isImporting) {
            return;
        }

        $verification = $this->currentVerification();

        if ($verification === null) {
            return;
        }

        try {
            $verificationService->reject($this->user(), $verification);
        } catch (AuthorizationException|InvalidArgumentException $exception) {
            $this->addError('import', $exception->getMessage());

            return;
        }

        $this->resetCard();
        session()->flash('status', __('csv_verification.card.reject_success'));
    }

    public function removeFile(): void
    {
        if ($this->cardPhase() === CsvVerificationCardPhase::Verifying || $this->isImporting) {
            return;
        }

        $this->resetCard();
    }

    public function cardPhase(): CsvVerificationCardPhase
    {
        if ($this->isImporting) {
            return CsvVerificationCardPhase::Importing;
        }

        $verification = $this->currentVerification();

        if ($verification !== null) {
            return match ($verification->status) {
                VerificationStatus::Pending,
                VerificationStatus::Processing => CsvVerificationCardPhase::Verifying,
                VerificationStatus::Ready => CsvVerificationCardPhase::Ready,
                VerificationStatus::Failed => CsvVerificationCardPhase::Invalid,
                default => CsvVerificationCardPhase::Empty,
            };
        }

        if ($this->csvFile !== null) {
            return CsvVerificationCardPhase::FileSelected;
        }

        return CsvVerificationCardPhase::Empty;
    }

    #[Computed]
    public function centerName(): string
    {
        return $this->center()->name;
    }

    #[Computed]
    public function centerLabel(): string
    {
        if ($this->user()->isOwner()) {
            return __('csv_verification.card.center_label');
        }

        return __('csv_verification.card.assigned_center_label');
    }

    #[Computed]
    public function verification(): ?ImportVerification
    {
        return $this->currentVerification();
    }

    #[Computed]
    public function summary(): ?VerificationSummaryData
    {
        $verification = $this->currentVerification();

        if ($verification === null || $verification->status !== VerificationStatus::Ready) {
            return null;
        }

        $verification->loadMissing('center');

        return app(VerificationSummaryService::class)->build($verification);
    }

    #[Computed]
    public function importModes(): array
    {
        return ImportMode::availableFor($this->user());
    }

    #[Computed]
    public function isManagerView(): bool
    {
        return $this->user()->hasRole(RoleName::CenterManager);
    }

    #[Computed]
    public function isCorrectionMode(): bool
    {
        return $this->importMode === ImportMode::Correction->value;
    }

    #[Computed]
    public function commitActionLabel(): string
    {
        if ($this->isCorrectionMode) {
            return __('csv_verification.card.submit_correction');
        }

        return __('csv_verification.card.import');
    }

    #[Computed]
    public function commitActionLoadingLabel(): string
    {
        if ($this->isCorrectionMode) {
            return __('csv_verification.card.submitting_correction');
        }

        return __('csv_verification.card.importing');
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    public function render(): View
    {
        return view('livewire.csv-verification.csv-verification-card');
    }

    private function resetCard(): void
    {
        $this->csvFile = null;
        $this->verificationToken = null;
        $this->notifyOwner = false;
        $this->isImporting = false;
        $this->resetErrorBag();
    }

    private function currentVerification(): ?ImportVerification
    {
        if ($this->verificationToken === null) {
            return null;
        }

        return app(VerificationService::class)->findForUser(
            $this->verificationToken,
            $this->user(),
            $this->center()->id,
        );
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function center(): Center
    {
        $user = $this->user();

        if ($user->isOwner()) {
            $context = app(ActiveCenterContextService::class)->resolve($user);

            return Center::query()->findOrFail($context?->centerId);
        }

        return Center::query()->findOrFail($user->center_id);
    }
}
