<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_event_id')->unique();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->index('provider_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex(['provider_message_id']);
        });

        Schema::dropIfExists('whatsapp_webhook_events');
    }
};
