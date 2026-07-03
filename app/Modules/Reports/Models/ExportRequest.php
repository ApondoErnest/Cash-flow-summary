<?php

declare(strict_types=1);

namespace App\Modules\Reports\Models;

use App\Models\User;
use App\Modules\Centers\Models\Center;
use App\Modules\Reports\Enums\ExportFormat;
use App\Modules\Reports\Enums\ExportRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportRequest extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'center_id',
        'report_type',
        'filters',
        'format',
        'status',
        'storage_path',
        'expires_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'format' => ExportFormat::class,
            'status' => ExportRequestStatus::class,
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function isExpired(): bool
    {
        if ($this->status === ExportRequestStatus::Expired) {
            return true;
        }

        return $this->status === ExportRequestStatus::Completed
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }
}
