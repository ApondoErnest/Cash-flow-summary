<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappWebhookEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider_event_id',
        'payload',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
