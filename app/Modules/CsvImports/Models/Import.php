<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Models;

use App\Models\Concerns\HasCenterScope;
use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\CsvImports\Enums\ImportStatus;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\CsvVerification\Models\ImportVerification;
use App\Modules\Reports\Models\Anomaly;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasCenterScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'center_id',
        'import_verification_id',
        'uploaded_by',
        'approved_by',
        'import_mode',
        'source_language',
        'original_filename',
        'storage_path',
        'file_hash',
        'file_size',
        'encoding',
        'delimiter',
        'reported_period',
        'actual_period_start',
        'actual_period_end',
        'declared_count',
        'parsed_count',
        'invalid_count',
        'duplicate_within_file_count',
        'historical_duplicate_count',
        'new_master_count',
        'source_ht',
        'source_vat',
        'source_ttc',
        'calculated_ht',
        'calculated_vat',
        'calculated_ttc',
        'status',
        'warnings',
        'processing_started_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'import_mode' => ImportMode::class,
            'status' => ImportStatus::class,
            'file_size' => 'integer',
            'declared_count' => 'integer',
            'parsed_count' => 'integer',
            'invalid_count' => 'integer',
            'duplicate_within_file_count' => 'integer',
            'historical_duplicate_count' => 'integer',
            'new_master_count' => 'integer',
            'source_ht' => 'decimal:2',
            'source_vat' => 'decimal:2',
            'source_ttc' => 'decimal:2',
            'calculated_ht' => 'decimal:2',
            'calculated_vat' => 'decimal:2',
            'calculated_ttc' => 'decimal:2',
            'warnings' => 'array',
            'actual_period_start' => 'date',
            'actual_period_end' => 'date',
            'processing_started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function importVerification(): BelongsTo
    {
        return $this->belongsTo(ImportVerification::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class);
    }

    public function dayComparisons(): HasMany
    {
        return $this->hasMany(ImportDayComparison::class);
    }

    public function anomalies(): HasMany
    {
        return $this->hasMany(Anomaly::class);
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class);
    }
}
