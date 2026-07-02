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
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->foreignId('center_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->string('recipient_phone');
            $table->string('template_name')->nullable();
            $table->json('payload_summary');
            $table->enum('status', [
                'queued',
                'sent',
                'delivered',
                'read',
                'failed',
            ])->default('queued');
            $table->string('provider_message_id')->nullable();
            $table->text('error_reason')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['center_id', 'status']);
            $table->index(['import_id', 'event_type']);
            $table->index('status');
        });

        Schema::create('internal_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('center_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->nullableMorphs('related');
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['center_id', 'created_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_notifications');
        Schema::dropIfExists('whatsapp_messages');
    }
};
