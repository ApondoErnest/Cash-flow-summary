<?php

declare(strict_types=1);

namespace App\Modules\Centers\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Center extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'address',
        'city',
        'region',
        'phone',
        'default_language',
        'submission_deadline',
        'whatsapp_summary_time',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Configured WhatsApp summary send time (H:i), defaulting to 18:00 when unset.
     */
    public function resolvedWhatsappSummaryTime(): string
    {
        $time = $this->whatsapp_summary_time;

        if (is_string($time) && trim($time) !== '') {
            return substr($time, 0, 5);
        }

        return substr((string) config('whatsapp.default_summary_time', '18:00'), 0, 5);
    }

    public function operatingCalendars(): HasMany
    {
        return $this->hasMany(CenterOperatingCalendar::class);
    }

    public function calendarExceptions(): HasMany
    {
        return $this->hasMany(CenterCalendarException::class);
    }

    public function assignedUsers(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
