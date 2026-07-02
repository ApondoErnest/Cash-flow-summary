<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Models;

use App\Models\Concerns\HasCenterScope;
use App\Modules\Centers\Models\Center;
use App\Modules\CsvImports\Enums\DuplicateType;
use App\Modules\CsvImports\Enums\ImportRowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    use HasCenterScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'import_id',
        'center_id',
        'source_row_number',
        'business_date',
        'original_values',
        'canonical_values',
        'raw_row_checksum',
        'exact_canonical_hash',
        'similarity_fingerprint',
        'normalization_policy_version',
        'master_record_id',
        'row_status',
        'duplicate_type',
        'duplicate_of_import_row_id',
        'validation_errors',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_row_number' => 'integer',
            'business_date' => 'date',
            'original_values' => 'array',
            'canonical_values' => 'array',
            'row_status' => ImportRowStatus::class,
            'duplicate_type' => DuplicateType::class,
            'validation_errors' => 'array',
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

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_import_row_id');
    }

    public function masterRecord(): BelongsTo
    {
        return $this->belongsTo(MasterCashFlowRecord::class, 'master_record_id');
    }
}
