<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Models;

use App\Models\Concerns\HasCenterScope;
use App\Modules\Centers\Models\Center;
use App\Modules\CsvImports\Enums\DayComparisonResult;
use App\Modules\DailyVersions\Models\DailyVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportDayComparison extends Model
{
    use HasCenterScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'import_id',
        'center_id',
        'business_date',
        'comparison_result',
        'existing_version_id',
        'proposed_version_id',
        'existing_ht',
        'existing_vat',
        'existing_ttc',
        'proposed_ht',
        'proposed_vat',
        'proposed_ttc',
        'record_count_delta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'comparison_result' => DayComparisonResult::class,
            'existing_ht' => 'decimal:2',
            'existing_vat' => 'decimal:2',
            'existing_ttc' => 'decimal:2',
            'proposed_ht' => 'decimal:2',
            'proposed_vat' => 'decimal:2',
            'proposed_ttc' => 'decimal:2',
            'record_count_delta' => 'integer',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function existingVersion(): BelongsTo
    {
        return $this->belongsTo(DailyVersion::class, 'existing_version_id');
    }

    public function proposedVersion(): BelongsTo
    {
        return $this->belongsTo(DailyVersion::class, 'proposed_version_id');
    }
}
