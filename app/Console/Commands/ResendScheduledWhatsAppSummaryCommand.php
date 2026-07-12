<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Centers\Models\Center;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\WhatsApp\Enums\WhatsappEventType;
use App\Modules\WhatsApp\Services\WhatsAppNotificationService;
use App\Modules\WhatsApp\Support\WhatsAppTimezone;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class ResendScheduledWhatsAppSummaryCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'whatsapp:resend-scheduled-summary
                            {--center= : Center ID (required)}
                            {--date= : Business date Y-m-d in the organization timezone (default: yesterday)}
                            {--cadence=daily : daily|weekly|monthly|yearly}
                            {--force : Rebuild payload and re-queue even if already sent}';

    /**
     * @var string
     */
    protected $description = 'Rebuild and queue a scheduled WhatsApp activity summary for a center/date';

    public function handle(
        SettingsService $settingsService,
        WhatsAppNotificationService $notificationService,
    ): int {
        $centerId = (int) $this->option('center');

        if ($centerId < 1) {
            $this->components->error('Pass --center=<id>.');

            return self::FAILURE;
        }

        /** @var Center|null $center */
        $center = Center::query()->with('organization')->find($centerId);

        if ($center === null) {
            $this->components->error("Center {$centerId} not found.");

            return self::FAILURE;
        }

        if (! $settingsService->whatsAppOutboundConfigured((int) $center->organization_id)) {
            $this->components->error('WhatsApp outbound is not configured for this organization.');

            return self::FAILURE;
        }

        $eventType = $this->resolveCadence((string) $this->option('cadence'));

        if ($eventType === null) {
            $this->components->error('Invalid --cadence. Use daily, weekly, monthly, or yearly.');

            return self::FAILURE;
        }

        $timezone = WhatsAppTimezone::forCenter($center);
        $dateOption = $this->option('date');
        $date = is_string($dateOption) && $dateOption !== ''
            ? $dateOption
            : now()->timezone($timezone)->subDay()->toDateString();

        $sendTime = $center->resolvedWhatsappSummaryTime();

        try {
            $moment = Carbon::parse("{$date} {$sendTime}:00", $timezone);
        } catch (\Throwable) {
            $this->components->error('Invalid --date. Use Y-m-d.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $message = $notificationService->queueScheduledSummary($center, $eventType, $moment, $force);

        if ($message === null) {
            $this->components->error('Could not prepare summary message.');

            return self::FAILURE;
        }

        $rows = $message->payload_summary['row_count'] ?? '—';
        $ttc = $message->payload_summary['footer_ttc'] ?? '—';

        $this->components->info(sprintf(
            'Queued %s for center %d (%s) period %s — rows=%s TTC=%s [tz=%s force=%s].',
            $eventType->value,
            $center->id,
            $center->name,
            $message->payload_summary['period'] ?? $date,
            $rows,
            $ttc,
            $timezone,
            $force ? 'yes' : 'no',
        ));

        return self::SUCCESS;
    }

    private function resolveCadence(string $cadence): ?WhatsappEventType
    {
        return match (strtolower(trim($cadence))) {
            'daily', 'daily_summary' => WhatsappEventType::DailySummary,
            'weekly', 'weekly_summary' => WhatsappEventType::WeeklySummary,
            'monthly', 'monthly_summary' => WhatsappEventType::MonthlySummary,
            'yearly', 'yearly_summary' => WhatsappEventType::YearlySummary,
            default => null,
        };
    }
}
