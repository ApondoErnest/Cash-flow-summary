<?php

declare(strict_types=1);

namespace App\Modules\Centers\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationFactory> */
    use HasFactory;
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'currency',
        'timezone',
        'default_language',
        'contact_details',
        'logo_path',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contact_details' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function centers(): HasMany
    {
        return $this->hasMany(Center::class);
    }
}
