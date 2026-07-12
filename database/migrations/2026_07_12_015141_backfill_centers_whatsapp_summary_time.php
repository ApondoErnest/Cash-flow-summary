<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $default = substr((string) config('whatsapp.default_summary_time', '18:00'), 0, 5);

        DB::table('centers')
            ->whereNull('whatsapp_summary_time')
            ->update(['whatsapp_summary_time' => $default]);
    }

    public function down(): void
    {
        // Intentionally left blank — backfilled times remain explicit.
    }
};
