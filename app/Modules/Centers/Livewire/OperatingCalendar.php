<?php

declare(strict_types=1);

namespace App\Modules\Centers\Livewire;

use App\Enums\CalendarExceptionType;
use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\CenterCalendarException;
use App\Modules\Centers\Services\OperatingCalendarService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class OperatingCalendar extends Component
{
    use AuthorizesRequests;

    public Center $center;

    /**
     * @var array<int, array{is_open: bool, open_time: string, close_time: string}>
     */
    public array $weeklyDays = [];

    public string $exception_date = '';

    public string $exception_type = 'holiday';

    public string $exception_open_time = '';

    public string $exception_close_time = '';

    public string $exception_notes = '';

    public ?int $editingExceptionId = null;

    public function mount(Center $center, OperatingCalendarService $calendarService): void
    {
        $this->authorize('update', $center);

        $this->center = $center;
        $this->weeklyDays = $calendarService->weeklyScheduleForForm($center);
    }

    public function saveWeeklySchedule(OperatingCalendarService $calendarService): void
    {
        $this->authorize('update', $this->center);

        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $calendarService->updateWeeklySchedule($this->center, $user, $this->weeklyDays);

        session()->flash('status', __('center.calendar.weekly_saved'));
    }

    public function saveException(OperatingCalendarService $calendarService): void
    {
        $this->authorize('update', $this->center);

        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $validated = $this->validate(
            $this->exceptionRules(),
            [],
            $this->exceptionValidationAttributes(),
        );

        $payload = [
            'exception_date' => $validated['exception_date'],
            'type' => $validated['exception_type'],
            'open_time' => $validated['exception_type'] === CalendarExceptionType::SpecialOpen->value
                ? $this->normalizeTime($validated['exception_open_time'])
                : null,
            'close_time' => $validated['exception_type'] === CalendarExceptionType::SpecialOpen->value
                ? $this->normalizeTime($validated['exception_close_time'])
                : null,
            'notes' => $validated['exception_notes'] !== '' ? $validated['exception_notes'] : null,
        ];

        if ($this->editingExceptionId !== null) {
            $exception = CenterCalendarException::query()->findOrFail($this->editingExceptionId);
            $calendarService->updateException($exception, $this->center, $user, $payload);
            session()->flash('status', __('center.calendar.exception_updated'));
        } else {
            $calendarService->createException($this->center, $user, $payload);
            session()->flash('status', __('center.calendar.exception_created'));
        }

        $this->resetExceptionForm();
    }

    public function editException(int $exceptionId, OperatingCalendarService $calendarService): void
    {
        $this->authorize('update', $this->center);

        $exception = $this->center->calendarExceptions()->findOrFail($exceptionId);

        $this->editingExceptionId = $exception->id;
        $this->exception_date = $exception->exception_date->format('Y-m-d');
        $this->exception_type = $exception->type;
        $this->exception_open_time = $calendarService->formatTimeForInput($exception->open_time);
        $this->exception_close_time = $calendarService->formatTimeForInput($exception->close_time);
        $this->exception_notes = (string) ($exception->notes ?? '');
    }

    public function cancelExceptionEdit(): void
    {
        $this->resetExceptionForm();
    }

    public function deleteException(
        int $exceptionId,
        OperatingCalendarService $calendarService,
    ): void {
        $this->authorize('update', $this->center);

        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $exception = $this->center->calendarExceptions()->findOrFail($exceptionId);
        $calendarService->deleteException($exception, $this->center, $user);

        if ($this->editingExceptionId === $exceptionId) {
            $this->resetExceptionForm();
        }

        session()->flash('status', __('center.calendar.exception_deleted'));
    }

    public function render(OperatingCalendarService $calendarService): View
    {
        return view('livewire.centers.operating-calendar', [
            'displayDayOrder' => $calendarService->displayDayOrder(),
            'dayLabel' => static fn (int $dayOfWeek): string => $calendarService->dayLabel($dayOfWeek),
            'exceptions' => $calendarService->exceptionsFor($this->center),
            'exceptionTypeLabel' => static fn (string $type): string => $calendarService->exceptionTypeLabel($type),
            'formatTimeForDisplay' => static fn (mixed $time): ?string => $calendarService->formatTimeForDisplay($time),
        ])->title(__('center.calendar.title', ['center' => $this->center->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function exceptionRules(): array
    {
        $ignoreId = $this->editingExceptionId;

        return [
            'exception_date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail) use ($ignoreId): void {
                    $exists = CenterCalendarException::query()
                        ->where('center_id', $this->center->id)
                        ->whereDate('exception_date', $value)
                        ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                        ->exists();

                    if ($exists) {
                        $fail(__('center.calendar.validation.date_taken'));
                    }
                },
            ],
            'exception_type' => ['required', 'in:'.implode(',', CalendarExceptionType::values())],
            'exception_open_time' => [
                'nullable',
                'required_if:exception_type,'.CalendarExceptionType::SpecialOpen->value,
                'date_format:H:i',
            ],
            'exception_close_time' => [
                'nullable',
                'required_if:exception_type,'.CalendarExceptionType::SpecialOpen->value,
                'date_format:H:i',
                'after:exception_open_time',
            ],
            'exception_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function exceptionValidationAttributes(): array
    {
        return [
            'exception_date' => __('center.calendar.fields.exception_date'),
            'exception_type' => __('center.calendar.fields.exception_type'),
            'exception_open_time' => __('center.calendar.fields.open_time'),
            'exception_close_time' => __('center.calendar.fields.close_time'),
            'exception_notes' => __('center.calendar.fields.notes'),
        ];
    }

    private function resetExceptionForm(): void
    {
        $this->editingExceptionId = null;
        $this->exception_date = '';
        $this->exception_type = CalendarExceptionType::Holiday->value;
        $this->exception_open_time = '';
        $this->exception_close_time = '';
        $this->exception_notes = '';
        $this->resetValidation();
    }

    private function normalizeTime(string $time): ?string
    {
        $time = trim($time);

        return $time === '' ? null : (strlen($time) === 5 ? "{$time}:00" : $time);
    }
}
