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
        Schema::create('import_verifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('center_id')->constrained()->cascadeOnDelete();
            $table->enum('import_mode', ['operational', 'historical', 'correction']);
            $table->boolean('notify_owner')->default(false);
            $table->string('original_filename');
            $table->string('temp_storage_path');
            $table->unsignedBigInteger('file_size');
            $table->string('file_hash', 64);
            $table->string('source_language', 5)->nullable();
            $table->string('encoding')->nullable();
            $table->char('delimiter', 1)->nullable();
            $table->string('reported_period')->nullable();
            $table->date('actual_period_start')->nullable();
            $table->date('actual_period_end')->nullable();
            $table->json('footer_summary')->nullable();
            $table->json('validation_result')->nullable();
            $table->json('row_stats')->nullable();
            $table->json('duplicate_summary')->nullable();
            $table->enum('status', [
                'pending',
                'processing',
                'ready',
                'imported',
                'rejected',
                'expired',
                'failed',
            ])->default('pending');
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('import_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['user_id', 'center_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_verifications');
    }
};
