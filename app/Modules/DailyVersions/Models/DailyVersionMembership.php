<?php

declare(strict_types=1);

namespace App\Modules\DailyVersions\Models;

use App\Modules\CsvImports\Models\MasterCashFlowRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyVersionMembership extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'daily_version_id',
        'master_cash_flow_record_id',
    ];

    public function dailyVersion(): BelongsTo
    {
        return $this->belongsTo(DailyVersion::class);
    }

    public function masterCashFlowRecord(): BelongsTo
    {
        return $this->belongsTo(MasterCashFlowRecord::class);
    }
}
