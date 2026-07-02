<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Models;

use App\Models\Concerns\HasCenterScope;
use App\Modules\Centers\Models\Center;
use App\Modules\CsvImports\Enums\CompletionStatus;
use App\Modules\CsvImports\Enums\FinancialStatus;
use App\Modules\DailyVersions\Models\DailyVersionMembership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterCashFlowRecord extends Model
{
    use HasCenterScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'center_id',
        'registration_date',
        'registration_time',
        'completion_date',
        'customer_name',
        'customer_name_normalized',
        'category_code',
        'inspection_type_code',
        'licence_plate',
        'licence_plate_normalized',
        'net_amount',
        'vat_amount',
        'gross_amount',
        'completion_status',
        'financial_status',
        'exact_canonical_hash',
        'normalization_policy_version',
        'first_import_id',
        'first_import_row_id',
        'first_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registration_date' => 'date',
            'completion_date' => 'date',
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'completion_status' => CompletionStatus::class,
            'financial_status' => FinancialStatus::class,
            'first_seen_at' => 'datetime',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function firstImport(): BelongsTo
    {
        return $this->belongsTo(Import::class, 'first_import_id');
    }

    public function firstImportRow(): BelongsTo
    {
        return $this->belongsTo(ImportRow::class, 'first_import_row_id');
    }

    public function importRows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'master_record_id');
    }

    public function dailyVersionMemberships(): HasMany
    {
        return $this->hasMany(DailyVersionMembership::class);
    }
}
