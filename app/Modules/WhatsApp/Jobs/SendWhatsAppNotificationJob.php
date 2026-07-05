<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\WhatsApp\Enums\WhatsappMessageStatus;
use App\Modules\WhatsApp\Exceptions\WhatsAppApiException;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Modules\WhatsApp\Services\WhatsAppNotificationService;
use App\Support\Center\JobCenterContextService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendWhatsAppNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries;

    /** @var list<int> */
    public array $backoff;

    public function __construct(
        public readonly int $whatsappMessageId,
    ) {
        $this->tries = (int) config('whatsapp.max_attempts', 3);
        $this->backoff = array_map(
            intval(...),
            config('whatsapp.retry_backoff_seconds', [60, 300, 900]),
        );
    }

    public function handle(
        WhatsAppNotificationService $notificationService,
        JobCenterContextService $jobCenterContextService,
    ): void {
        $message = WhatsappMessage::query()->find($this->whatsappMessageId);

        if ($message === null || $message->center_id === null) {
            return;
        }

        if ($message->status !== WhatsappMessageStatus::Queued) {
            return;
        }

        try {
            $jobCenterContextService->runForCenter(
                (int) $message->center_id,
                fn () => $notificationService->sendMessage($message),
            );
        } catch (WhatsAppApiException $exception) {
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $message = WhatsappMessage::query()->find($this->whatsappMessageId);

        if ($message === null || $message->status !== WhatsappMessageStatus::Queued) {
            return;
        }

        $message->forceFill([
            'status' => WhatsappMessageStatus::Failed,
            'error_reason' => $exception?->getMessage() ?? __('whatsapp.errors.send_failed'),
        ])->save();
    }
}
