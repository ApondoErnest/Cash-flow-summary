<?php

declare(strict_types=1);

namespace App\Modules\Centers\Services;

use App\Enums\CalendarExceptionType;
use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\CenterCalendarException;
use App\Modules\Centers\Models\CenterOperatingCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class OperatingCalendarService
{
    /**
     * Monday-first display order using PHP day-of-week values (0 = Sunday).
     *
     * @return list<int>
     */
    public function displayDayOrder(): array
    {
        return [1, 2, 3, 4, 5, 6, 0];
    }

    public function dayLabel(int $dayOfWeek): string
    {
        $labels = __('center.calendar.days');

        return is_array($labels) ? ($labels[$dayOfWeek] ?? (string) $dayOfWeek) : (string) $dayOfWeek;
    }

    /**
     * @return Collection<int, CenterOperatingCalendar>
     */
    public function weeklySchedule(Center $center): Collection
    {
        $this->ensureWeeklySchedule($center);

        return $center->operatingCalendars()
            ->orderByRaw('CASE day_of_week WHEN 0 THEN 7 ELSE day_of_week END')
            ->get()
            ->keyBy('day_of_week');
    }

    /**
     * @return array<int, array{is_open: bool, open_time: string, close_time: string}>
     */
    public function weeklyScheduleForForm(Center $center): array
    {
        $schedule = [];

        foreach ($this->weeklySchedule($center) as $dayOfWeek => $row) {
            $schedule[(int) $dayOfWeek] = [
                'is_open' => (bool) $row->is_open,
                'open_time' => $this->formatTimeForInput($row->open_time),
                'close_time' => $this->formatTimeForInput($row->close_time),
            ];
        }

        return $schedule;
    }

    public function ensureWeeklySchedule(Center $center): void
    {
        for ($dayOfWeek = 0; $dayOfWeek <= 6; $dayOfWeek++) {
            CenterOperatingCalendar::query()->firstOrCreate(
                [
                    'center_id' => $center->id,
                    'day_of_week' => $dayOfWeek,
                ],
                [
                    'is_open' => $dayOfWeek !== 0,
                    'open_time' => $dayOfWeek !== 0 ? '08:00:00' : null,
                    'close_time' => $dayOfWeek !== 0 ? '18:00:00' : null,
                ],
            );
        }
    }

    /**
     * @param  array<int, array{is_open: bool, open_time?: string|null, close_time?: string|null}>  $days
     */
    public function updateWeeklySchedule(Center $center, User $owner, array $days): void
    {
        app(CenterService::class)->assertBelongsToOrganization($center, $owner);

        foreach ($this->displayDayOrder() as $dayOfWeek) {
            if (! array_key_exists($dayOfWeek, $days)) {
                continue;
            }

            $day = $days[$dayOfWeek];
            $isOpen = (bool) ($day['is_open'] ?? false);
            $openTime = $this->normalizeTime($day['open_time'] ?? null);
            $closeTime = $this->normalizeTime($day['close_time'] ?? null);

            if ($isOpen && ($openTime === null || $closeTime === null)) {
                throw ValidationException::withMessages([
                    "weeklyDays.{$dayOfWeek}.open_time" => __('center.calendar.validation.open_hours_required'),
                ]);
            }

            if ($isOpen && $openTime !== null && $closeTime !== null && $closeTime <= $openTime) {
                throw ValidationException::withMessages([
                    "weeklyDays.{$dayOfWeek}.close_time" => __('center.calendar.validation.close_after_open'),
                ]);
            }

            CenterOperatingCalendar::query()->updateOrCreate(
                [
                    'center_id' => $center->id,
                    'day_of_week' => $dayOfWeek,
                ],
                [
                    'is_open' => $isOpen,
                    'open_time' => $isOpen ? $openTime : null,
                    'close_time' => $isOpen ? $closeTime : null,
                ],
            );
        }
    }

    /**
     * @return Collection<int, CenterCalendarException>
     */
    public function exceptionsFor(Center $center): Collection
    {
        return $center->calendarExceptions()
            ->orderBy('exception_date')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createException(Center $center, User $owner, array $data): CenterCalendarException
    {
        app(CenterService::class)->assertBelongsToOrganization($center, $owner);
        $this->assertUniqueExceptionDate($center, (string) $data['exception_date']);

        return CenterCalendarException::query()->create([
            'center_id' => $center->id,
            'exception_date' => $data['exception_date'],
            'type' => $data['type'],
            'open_time' => $data['open_time'] ?? null,
            'close_time' => $data['close_time'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateException(
        CenterCalendarException $exception,
        Center $center,
        User $owner,
        array $data,
    ): CenterCalendarException {
        app(CenterService::class)->assertBelongsToOrganization($center, $owner);

        if ((int) $exception->center_id !== (int) $center->id) {
            throw ValidationException::withMessages([
                'exception_date' => __('center.calendar.validation.invalid_exception'),
            ]);
        }

        $this->assertUniqueExceptionDate($center, (string) $data['exception_date'], $exception->id);

        $exception->fill([
            'exception_date' => $data['exception_date'],
            'type' => $data['type'],
            'open_time' => $data['open_time'] ?? null,
            'close_time' => $data['close_time'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->save();

        return $exception->fresh();
    }

    public function deleteException(CenterCalendarException $exception, Center $center, User $owner): void
    {
        app(CenterService::class)->assertBelongsToOrganization($center, $owner);

        if ((int) $exception->center_id !== (int) $center->id) {
            throw ValidationException::withMessages([
                'exception_date' => __('center.calendar.validation.invalid_exception'),
            ]);
        }

        $exception->delete();
    }

    public function exceptionTypeLabel(string $type): string
    {
        return match ($type) {
            CalendarExceptionType::Holiday->value => __('center.calendar.exception_types.holiday'),
            CalendarExceptionType::Closure->value => __('center.calendar.exception_types.closure'),
            CalendarExceptionType::SpecialOpen->value => __('center.calendar.exception_types.special_open'),
            default => $type,
        };
    }

    public function isOperatingDay(Center $center, Carbon $date): bool
    {
        $exception = CenterCalendarException::query()
            ->where('center_id', $center->id)
            ->whereDate('exception_date', $date->toDateString())
            ->first();

        if ($exception !== null) {
            return match ((string) $exception->type) {
                CalendarExceptionType::Holiday->value,
                CalendarExceptionType::Closure->value => false,
                CalendarExceptionType::SpecialOpen->value => true,
                default => false,
            };
        }

        $schedule = CenterOperatingCalendar::query()
            ->where('center_id', $center->id)
            ->where('day_of_week', $date->dayOfWeek)
            ->first();

        return $schedule?->is_open ?? true;
    }

    public function formatTimeForInput(mixed $time): string
    {
        if ($time === null || $time === '') {
            return '';
        }

        return substr((string) $time, 0, 5);
    }

    public function formatTimeForDisplay(mixed $time): ?string
    {
        $value = $this->formatTimeForInput($time);

        return $value !== '' ? $value : null;
    }

    private function normalizeTime(?string $time): ?string
    {
        $time = trim((string) $time);

        if ($time === '') {
            return null;
        }

        return strlen($time) === 5 ? "{$time}:00" : $time;
    }

    private function assertUniqueExceptionDate(Center $center, string $date, ?int $ignoreId = null): void
    {
        $exists = CenterCalendarException::query()
            ->where('center_id', $center->id)
            ->whereDate('exception_date', Carbon::parse($date)->toDateString())
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'exception_date' => __('center.calendar.validation.date_taken'),
            ]);
        }
    }
}
