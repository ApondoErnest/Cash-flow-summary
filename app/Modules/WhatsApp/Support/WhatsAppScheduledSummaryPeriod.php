<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Support;

use Illuminate\Support\Carbon;

final readonly class WhatsAppScheduledSummaryPeriod
{
    public function __construct(
        public Carbon $start,
        public Carbon $end,
        public string $label,
        public string $periodKey,
    ) {}
}
