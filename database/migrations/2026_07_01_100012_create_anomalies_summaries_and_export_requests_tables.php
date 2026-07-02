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
        Schema::create('anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->text('description');
            $table->json('metadata');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['center_id', 'resolved_at']);
            $table->index(['import_id', 'type']);
        });

        Schema::create('daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->foreignId('daily_version_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('record_count');
            $table->decimal('total_ht', 15, 2);
            $table->decimal('total_vat', 15, 2);
            $table->decimal('total_ttc', 15, 2);
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['center_id', 'business_date']);
            $table->index('daily_version_id');
        });

        Schema::create('summary_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_summary_id')->constrained()->cascadeOnDelete();
            $table->string('breakdown_key');
            $table->string('breakdown_value');
            $table->unsignedInteger('record_count');
            $table->decimal('total_ht', 15, 2);
            $table->decimal('total_vat', 15, 2);
            $table->decimal('total_ttc', 15, 2);
            $table->timestamps();

            $table->unique(
                ['daily_summary_id', 'breakdown_key', 'breakdown_value'],
                'summary_breakdown_dimension_unique',
            );
        });

        Schema::create('export_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('center_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_type');
            $table->json('filters');
            $table->enum('format', ['csv', 'xlsx', 'pdf']);
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'expired',
            ])->default('pending');
            $table->string('storage_path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['center_id', 'created_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_requests');
        Schema::dropIfExists('summary_breakdowns');
        Schema::dropIfExists('daily_summaries');
        Schema::dropIfExists('anomalies');
    }
};
