<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centers', function (Blueprint $table) {
            $table->time('whatsapp_summary_time')->nullable()->after('submission_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('centers', function (Blueprint $table) {
            $table->dropColumn('whatsapp_summary_time');
        });
    }
};
