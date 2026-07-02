<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Models;

use App\Models\Concerns\HasCenterScope;
use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\CsvImports\Models\Import;
use App\Modules\CsvVerification\Enums\ImportMode;
use App\Modules\CsvVerification\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportVerification extends Model
{
    use HasCenterScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'token',
        'user_id',
        'center_id',
        'import_mode',
        'notify_owner',
        'original_filename',
        'temp_storage_path',
        'file_size',
        'file_hash',
        'source_language',
        'encoding',
        'delimiter',
        'reported_period',
        'actual_period_start',
        'actual_period_end',
        'footer_summary',
        'validation_result',
        'row_stats',
        'duplicate_summary',
        'status',
        'error_message',
        'import_id',
        'expires_at',
        'verified_at',
        'committed_at',
        'rejected_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'import_mode' => ImportMode::class,
            'notify_owner' => 'boolean',
            'file_size' => 'integer',
            'footer_summary' => 'array',
            'validation_result' => 'array',
            'row_stats' => 'array',
            'duplicate_summary' => 'array',
            'status' => VerificationStatus::class,
            'actual_period_start' => 'date',
            'actual_period_end' => 'date',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'committed_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
