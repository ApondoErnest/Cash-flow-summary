<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Models;

use App\Models\Concerns\HasCenterScope;
use App\Modules\Centers\Models\Center;
use App\Modules\CsvImports\Models\Import;
use App\Modules\WhatsApp\Enums\WhatsappMessageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessage extends Model
{
    use HasCenterScope;
    /**
     * @var list<string>
     */
    protected $fillable = [
        'idempotency_key',
        'center_id',
        'import_id',
        'event_type',
        'recipient_phone',
        'template_name',
        'payload_summary',
        'status',
        'provider_message_id',
        'error_reason',
        'retry_count',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload_summary' => 'array',
            'status' => WhatsappMessageStatus::class,
            'retry_count' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
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
