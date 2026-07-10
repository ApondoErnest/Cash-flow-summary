<?php

declare(strict_types=1);

namespace App\Modules\Dashboards\Services;

use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Services\OperatingCalendarService;
use App\Modules\DailyVersions\Models\ActiveDailySnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class SubmissionStatusService
{
    /**
     * @return list<string> ISO date strings for expected operating days missing an active snapshot
     */
    public function missingSubmissionDates(Center $center, Carbon $reference, int $lookbackDays = 14): array
    {
        $missing = [];
        $cursor = $reference->copy()->subDay();

        for ($index = 0; $index < $lookbackDays; $index++) {
            if (! $this->isOperatingDay($center, $cursor)) {
                $cursor->subDay();

                continue;
            }

            $date = $cursor->toDateString();

            $hasSnapshot = ActiveDailySnapshot::query()
                ->withoutCenterScope()
                ->where('center_id', $center->id)
                ->whereDate('business_date', $date)
                ->exists();

            if (! $hasSnapshot) {
                $missing[] = $date;
            }

            $cursor->subDay();
        }

        return array_reverse($missing);
    }

    /**
     * @return list<string> ISO date strings for expected operating days missing an active snapshot
     */
    public function missingSubmissionDatesBetween(Center $center, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $missing = [];
        $cursor = $rangeStart->copy()->startOfDay();
        $end = $rangeEnd->copy()->startOfDay();

        while ($cursor->lte($end)) {
            if ($this->isOperatingDay($center, $cursor)) {
                $date = $cursor->toDateString();

                $hasSnapshot = ActiveDailySnapshot::query()
                    ->withoutCenterScope()
                    ->where('center_id', $center->id)
                    ->whereDate('business_date', $date)
                    ->exists();

                if (! $hasSnapshot) {
                    $missing[] = $date;
                }
            }

            $cursor->addDay();
        }

        return $missing;
    }

    private function isOperatingDay(Center $center, Carbon $date): bool
    {
        return app(OperatingCalendarService::class)->isOperatingDay($center, $date);
    }
}
