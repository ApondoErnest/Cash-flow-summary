<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Models;

use App\Models\Concerns\HasCenterScope;
use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\CsvImports\Models\Import;
use App\Modules\DailyVersions\Enums\DailyVersionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DailyVersion extends Model
{
    use HasCenterScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'center_id',
        'business_date',
        'import_id',
        'version_number',
        'dataset_hash',
        'record_count',
        'total_ht',
        'total_vat',
        'total_ttc',
        'status',
        'previous_version_id',
        'revision_reason',
        'submitted_by',
        'approved_by',
        'approved_at',
        'rejected_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'version_number' => 'integer',
            'record_count' => 'integer',
            'total_ht' => 'decimal:2',
            'total_vat' => 'decimal:2',
            'total_ttc' => 'decimal:2',
            'status' => DailyVersionStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(DailyVersionMembership::class);
    }

    public function activeSnapshot(): HasOne
    {
        return $this->hasOne(ActiveDailySnapshot::class);
    }
}
