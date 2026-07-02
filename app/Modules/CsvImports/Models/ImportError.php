<?php

declare(strict_types=1);

namespace App\Modules\CsvImports\Models;

use App\Modules\CsvVerification\Models\ImportVerification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportError extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'import_id',
        'import_verification_id',
        'source_row_number',
        'field',
        'error_code',
        'original_value',
        'raw_row',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_row_number' => 'integer',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function importVerification(): BelongsTo
    {
        return $this->belongsTo(ImportVerification::class);
    }
}
