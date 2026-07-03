<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use App\Models\User;
use App\Modules\Centers\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'key',
        'value',
        'updated_by',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
