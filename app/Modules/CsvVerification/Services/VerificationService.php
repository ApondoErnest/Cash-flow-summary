<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Services;

use App\Models\User;
use App\Modules\AuditLogging\Models\AuditLog;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\ActiveCenterContextService;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use App\Modules\CsvVerification\Jobs\ProcessVerificationJob;
use App\Modules\CsvVerification\Models\ImportVerification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class VerificationService
{
    /**
     * @var list<VerificationStatus>
     */
    private const REJECTABLE_STATUSES = [
        VerificationStatus::Ready,
        VerificationStatus::Failed,
    ];

    public function __construct(
        private readonly ActiveCenterContextService $activeCenterContext,
        private readonly VerificationCleanupService $cleanupService,
    ) {}

    public function start(
        User $user,
        Center $center,
        UploadedFile $file,
        ImportMode $importMode,
        bool $notifyOwner = false,
    ): ImportVerification {
        $this->assertUserCanVerifyForCenter($user, $center);
        app(CorrectionSubmissionService::class)->assertModeAllowed($user, $importMode);
        $this->assertUploadIsValid($file);

        $token = (string) Str::uuid();
        $disk = (string) config('csv_verification.temp_disk', 'local');
        $directory = (string) config('csv_verification.temp_directory', 'temp/verifications');
        $tempStoragePath = trim($directory, '/')."/{$token}.csv";

        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new InvalidArgumentException('Unable to read uploaded CSV file.');
        }

        $fileHash = hash('sha256', $contents);
        $fileSize = strlen($contents);

        Storage::disk($disk)->put($tempStoragePath, $contents);

        try {
            $verification = DB::transaction(function () use (
                $user,
                $center,
                $file,
                $importMode,
                $notifyOwner,
                $token,
                $tempStoragePath,
                $fileHash,
                $fileSize,
            ): ImportVerification {
                return ImportVerification::query()->create([
                    'token' => $token,
                    'user_id' => $user->id,
                    'center_id' => $center->id,
                    'import_mode' => $importMode,
                    'notify_owner' => $notifyOwner,
                    'original_filename' => $file->getClientOriginalName(),
                    'temp_storage_path' => $tempStoragePath,
                    'file_size' => $fileSize,
                    'file_hash' => $fileHash,
                    'status' => VerificationStatus::Pending,
                    'expires_at' => now()->addMinutes($this->ttlMinutes()),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($tempStoragePath);

            throw $exception;
        }

        ProcessVerificationJob::dispatch($verification->token, $verification->center_id);

        return $verification;
    }

    public function findForUser(string $token, User $user, int $centerId): ?ImportVerification
    {
        return ImportVerification::query()
            ->where('token', $token)
            ->where('user_id', $user->id)
            ->where('center_id', $centerId)
            ->first();
    }

    public function isExpired(ImportVerification $verification): bool
    {
        return $verification->status === VerificationStatus::Expired
            || $verification->status === VerificationStatus::Rejected
            || $verification->expires_at->isPast();
    }

    public function reject(User $user, ImportVerification $verification): ImportVerification
    {
        $this->assertUserCanVerifyForCenter($user, $verification->center);

        if ((int) $verification->user_id !== (int) $user->id) {
            throw new AuthorizationException(__('center.cross_center_forbidden'));
        }

        if ($this->isExpired($verification)) {
            throw new InvalidArgumentException(__('csv_verification.verification.expired'));
        }

        if (! in_array($verification->status, self::REJECTABLE_STATUSES, true)) {
            throw new InvalidArgumentException(__('csv_verification.verification.reject_not_allowed'));
        }

        $this->cleanupService->deleteTempFile($verification);

        $verification->update([
            'status' => VerificationStatus::Rejected,
            'rejected_at' => now(),
        ]);

        AuditLog::query()->create([
            'user_id' => $user->id,
            'center_id' => $verification->center_id,
            'event' => 'verification.rejected',
            'resource_type' => ImportVerification::class,
            'resource_id' => $verification->id,
            'new_values' => [
                'token' => $verification->token,
                'filename' => $verification->original_filename,
            ],
        ]);

        return $verification->fresh();
    }

    public function ttlMinutes(): int
    {
        return max(1, (int) config('csv_verification.ttl_minutes', 120));
    }

    private function assertUserCanVerifyForCenter(User $user, Center $center): void
    {
        if ($user->isOwner()) {
            $activeCenter = $this->activeCenterContext->resolve($user);

            if ($activeCenter === null || $activeCenter->centerId !== (int) $center->id) {
                throw new AuthorizationException(__('center.active_center_invalid'));
            }

            return;
        }

        if ($user->isCenterStaff()) {
            if ((int) $user->center_id !== (int) $center->id) {
                throw new AuthorizationException(__('center.cross_center_forbidden'));
            }

            if ((int) $center->organization_id !== (int) $user->organization_id) {
                throw new AuthorizationException(__('center.assigned_invalid'));
            }

            return;
        }

        throw new AuthorizationException(__('center.not_applicable'));
    }

    private function assertUploadIsValid(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('Uploaded CSV file is invalid.');
        }

        if ($file->getSize() === false || (int) $file->getSize() <= 0) {
            throw new InvalidArgumentException('Uploaded CSV file is empty.');
        }
    }
}
