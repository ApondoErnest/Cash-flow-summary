<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Models;

use App\Models\Concerns\HasCenterScope;
use App\Modules\Centers\Models\Center;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActiveDailySnapshot extends Model
{
    use HasCenterScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'center_id',
        'business_date',
        'daily_version_id',
        'activated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'activated_at' => 'datetime',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function dailyVersion(): BelongsTo
    {
        return $this->belongsTo(DailyVersion::class);
    }
}
