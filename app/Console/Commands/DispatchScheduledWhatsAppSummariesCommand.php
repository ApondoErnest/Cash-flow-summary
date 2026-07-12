<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\OperatingCalendarService;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\WhatsApp\Services\WhatsAppNotificationService;
use App\Modules\WhatsApp\Support\WhatsAppCadenceResolver;
use App\Modules\WhatsApp\Support\WhatsAppTimezone;
use Illuminate\Console\Command;

final class DispatchScheduledWhatsAppSummariesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'whatsapp:dispatch-scheduled-summaries';

    /**
     * @var string
     */
    protected $description = 'Queue scheduled WhatsApp activity summaries for centers due at the current minute';

    public function handle(
        SettingsService $settingsService,
        OperatingCalendarService $operatingCalendarService,
        WhatsAppCadenceResolver $cadenceResolver,
        WhatsAppNotificationService $notificationService,
    ): int {
        $queued = 0;

        $centers = Center::query()
            ->where('is_active', true)
            ->with('organization')
            ->get();

        foreach ($centers as $center) {
            if (! $settingsService->whatsAppOutboundConfigured((int) $center->organization_id)) {
                continue;
            }

            $timezone = WhatsAppTimezone::forCenter($center);
            $moment = now()->timezone($timezone);
            $currentTime = $moment->format('H:i');

            if ($this->normalizedSummaryTime($center) !== $currentTime) {
                continue;
            }

            $operatingCalendarService->ensureWeeklySchedule($center);

            foreach ($cadenceResolver->dueCadences($center, $moment) as $cadence) {
                $message = $notificationService->queueScheduledSummary($center, $cadence, $moment);

                if ($message !== null && $message->wasRecentlyCreated) {
                    $queued++;
                }
            }
        }

        $this->components->info(sprintf(
            'Queued %d scheduled WhatsApp summar%s.',
            $queued,
            $queued === 1 ? 'y' : 'ies',
        ));

        return self::SUCCESS;
    }

    private function normalizedSummaryTime(Center $center): string
    {
        return $center->resolvedWhatsappSummaryTime();
    }
}
