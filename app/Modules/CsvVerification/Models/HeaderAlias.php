<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeaderAlias extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'csv_format_version_id',
        'canonical_field',
        'language',
        'source_header',
        'normalized_header',
        'is_required',
        'is_active',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function csvFormatVersion(): BelongsTo
    {
        return $this->belongsTo(CsvFormatVersion::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
