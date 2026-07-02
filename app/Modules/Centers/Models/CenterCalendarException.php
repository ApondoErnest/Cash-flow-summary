<?php

declare(strict_types=1);

namespace App\Modules\Centers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CenterCalendarException extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'center_id',
        'exception_date',
        'type',
        'open_time',
        'close_time',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exception_date' => 'date',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }
}
