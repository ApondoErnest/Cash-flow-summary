<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Modules\Centers\Models\Center;
use App\Modules\Dashboards\Services\ImportPeriodCategorySummaryService;
use App\Modules\Dashboards\Support\DashboardMoney;
use App\Modules\Reports\Services\ReportQueryService;
use App\Modules\WhatsApp\Enums\WhatsappEventType;
use App\Modules\WhatsApp\Support\WhatsAppScheduledSummaryPeriod;
use Illuminate\Support\Carbon;

final class WhatsAppScheduledSummaryService
{
    public function __construct(
        private readonly ReportQueryService $reportQueryService,
        private readonly ImportPeriodCategorySummaryService $categorySummaryService,
    ) {}

    public function periodFor(WhatsappEventType $cadence, Carbon $moment): WhatsAppScheduledSummaryPeriod
    {
        return match ($cadence) {
            WhatsappEventType::DailySummary => new WhatsAppScheduledSummaryPeriod(
                start: $moment->copy()->startOfDay(),
                end: $moment->copy(),
                label: $moment->format('d/m/Y'),
                periodKey: $moment->toDateString(),
            ),
            WhatsappEventType::WeeklySummary => $this->weeklyPeriod($moment),
            WhatsappEventType::MonthlySummary => new WhatsAppScheduledSummaryPeriod(
                start: $moment->copy()->startOfMonth()->startOfDay(),
                end: $moment->copy()->endOfDay(),
                label: $moment->copy()->startOfMonth()->format('d/m/Y')
                    .' – '.$moment->format('d/m/Y'),
                periodKey: $moment->format('Y-m'),
            ),
            WhatsappEventType::YearlySummary => new WhatsAppScheduledSummaryPeriod(
                start: $moment->copy()->startOfYear()->startOfDay(),
                end: $moment->copy()->endOfDay(),
                label: $moment->copy()->startOfYear()->format('d/m/Y')
                    .' – '.$moment->format('d/m/Y'),
                periodKey: $moment->format('Y'),
            ),
            default => throw new \InvalidArgumentException('Unsupported scheduled summary cadence.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayloadSummary(Center $center, WhatsappEventType $cadence, Carbon $moment): array
    {
        $period = $this->periodFor($cadence, $moment);
        $totals = $this->reportQueryService->totalsForDateRange(
            (int) $center->id,
            $period->start,
            $period->end,
        );

        return [
            'event_type' => $cadence->value,
            'cadence' => $cadence->value,
            'center_name' => $center->name,
            'period' => $period->label,
            'period_start' => $period->start->toDateString(),
            'period_end' => $period->end->toDateString(),
            'period_key' => $period->periodKey,
            'row_count' => $totals['recordCount'],
            'category_summary' => $this->categorySummaryService->formatSummaryForCenterInRange(
                (int) $center->id,
                $period->start,
                $period->end,
            ),
            'footer_ht' => DashboardMoney::format($totals['ht']),
            'footer_vat' => DashboardMoney::format($totals['vat']),
            'footer_ttc' => DashboardMoney::format($totals['ttc']),
        ];
    }

    private function weeklyPeriod(Carbon $moment): WhatsAppScheduledSummaryPeriod
    {
        $start = $moment->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $end = $moment->copy()->endOfDay();

        return new WhatsAppScheduledSummaryPeriod(
            start: $start,
            end: $end,
            label: $start->format('d/m/Y').' – '.$end->format('d/m/Y'),
            periodKey: $moment->format('o-\WW'),
        );
    }
}
