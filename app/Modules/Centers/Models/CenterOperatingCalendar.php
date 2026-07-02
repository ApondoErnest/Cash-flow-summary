<?php

declare(strict_types=1);

namespace App\Modules\Centers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CenterOperatingCalendar extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'center_id',
        'day_of_week',
        'is_open',
        'open_time',
        'close_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_open' => 'boolean',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }
}
