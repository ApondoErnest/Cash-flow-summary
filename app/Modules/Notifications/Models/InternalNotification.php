<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Models\User;
use App\Modules\Centers\Models\Center;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InternalNotification extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'center_id',
        'type',
        'title',
        'body',
        'read_at',
        'related_type',
        'related_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
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

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
