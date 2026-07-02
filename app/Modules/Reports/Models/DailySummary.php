<?php

declare(strict_types=1);

namespace App\Modules\Reports\Models;

use App\Models\Concerns\HasCenterScope;
use App\Modules\Centers\Models\Center;
use App\Modules\DailyVersions\Models\DailyVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailySummary extends Model
{
    use HasCenterScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'center_id',
        'business_date',
        'daily_version_id',
        'record_count',
        'total_ht',
        'total_vat',
        'total_ttc',
        'generated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'record_count' => 'integer',
            'total_ht' => 'decimal:2',
            'total_vat' => 'decimal:2',
            'total_ttc' => 'decimal:2',
            'generated_at' => 'datetime',
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

    public function breakdowns(): HasMany
    {
        return $this->hasMany(SummaryBreakdown::class);
    }
}
