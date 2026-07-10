<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Support;

use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\OperatingCalendarService;
use App\Modules\WhatsApp\Enums\WhatsappEventType;
use Illuminate\Support\Carbon;

final class WhatsAppCadenceResolver
{
    public function __construct(
        private readonly OperatingCalendarService $operatingCalendarService,
    ) {}

    /**
     * @return list<WhatsappEventType>
     */
    public function dueCadences(Center $center, Carbon $moment): array
    {
        $cadences = [];

        if ($this->operatingCalendarService->isOperatingDay($center, $moment)) {
            $cadences[] = WhatsappEventType::DailySummary;
        }

        if ($moment->dayOfWeek === Carbon::SATURDAY) {
            $cadences[] = WhatsappEventType::WeeklySummary;
        }

        if ($moment->isLastOfMonth()) {
            $cadences[] = WhatsappEventType::MonthlySummary;
        }

        if ($moment->month === 12 && $moment->day === 31) {
            $cadences[] = WhatsappEventType::YearlySummary;
        }

        return $cadences;
    }
}
