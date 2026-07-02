<?php

declare(strict_types=1);

namespace App\Modules\CsvVerification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CsvFormatVersion extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'version',
        'column_count',
        'delimiter',
        'encoding',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'column_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function headerAliases(): HasMany
    {
        return $this->hasMany(HeaderAlias::class);
    }
}
