<?php

declare(strict_types=1);

namespace App\Modules\Centers\Models;

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

    public function operatingCalendars(): HasMany
    {
        return $this->hasMany(CenterOperatingCalendar::class);
    }

    public function calendarExceptions(): HasMany
    {
        return $this->hasMany(CenterCalendarException::class);
    }
}
