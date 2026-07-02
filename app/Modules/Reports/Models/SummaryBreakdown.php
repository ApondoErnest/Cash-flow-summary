<?php

declare(strict_types=1);

namespace App\Modules\Reports\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SummaryBreakdown extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'daily_summary_id',
        'breakdown_key',
        'breakdown_value',
        'record_count',
        'total_ht',
        'total_vat',
        'total_ttc',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'record_count' => 'integer',
            'total_ht' => 'decimal:2',
            'total_vat' => 'decimal:2',
            'total_ttc' => 'decimal:2',
        ];
    }

    public function dailySummary(): BelongsTo
    {
        return $this->belongsTo(DailySummary::class);
    }
}
