<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\OperatingCalendarService;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\WhatsApp\Services\WhatsAppNotificationService;
use App\Modules\WhatsApp\Support\WhatsAppCadenceResolver;
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
        $moment = now()->timezone(config('app.timezone'));
        $currentTime = $moment->format('H:i');
        $queued = 0;

        $centers = Center::query()
            ->where('is_active', true)
            ->get();

        foreach ($centers as $center) {
            if (! $settingsService->whatsAppOutboundConfigured((int) $center->organization_id)) {
                continue;
            }

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
            'Queued %d scheduled WhatsApp summar%s for %s.',
            $queued,
            $queued === 1 ? 'y' : 'ies',
            $currentTime,
        ));

        return self::SUCCESS;
    }

    private function normalizedSummaryTime(Center $center): string
    {
        $time = $center->whatsapp_summary_time
            ?? (string) config('whatsapp.default_summary_time', '18:00');

        return substr((string) $time, 0, 5);
    }
}
