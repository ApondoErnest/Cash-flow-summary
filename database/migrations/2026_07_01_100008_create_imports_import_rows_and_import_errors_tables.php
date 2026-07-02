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
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_verification_id')->nullable()->constrained('import_verifications')->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('import_mode', ['operational', 'historical', 'correction']);
            $table->string('source_language', 5);
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('file_hash', 64);
            $table->unsignedBigInteger('file_size');
            $table->string('encoding')->nullable();
            $table->char('delimiter', 1)->nullable();
            $table->string('reported_period')->nullable();
            $table->date('actual_period_start')->nullable();
            $table->date('actual_period_end')->nullable();
            $table->unsignedInteger('declared_count')->default(0);
            $table->unsignedInteger('parsed_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->unsignedInteger('duplicate_within_file_count')->default(0);
            $table->unsignedInteger('historical_duplicate_count')->default(0);
            $table->unsignedInteger('new_master_count')->default(0);
            $table->decimal('source_ht', 15, 2)->default(0);
            $table->decimal('source_vat', 15, 2)->default(0);
            $table->decimal('source_ttc', 15, 2)->default(0);
            $table->decimal('calculated_ht', 15, 2)->default(0);
            $table->decimal('calculated_vat', 15, 2)->default(0);
            $table->decimal('calculated_ttc', 15, 2)->default(0);
            $table->enum('status', [
                'processing',
                'completed',
                'completed_with_duplicates',
                'completed_with_warnings',
                'exact_file_duplicate',
                'awaiting_owner_approval',
                'failed',
                'cancelled',
            ])->default('processing');
            $table->json('warnings')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['center_id', 'file_hash']);
            $table->index('status');
            $table->index(['center_id', 'created_at']);
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('center_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('source_row_number');
            $table->date('business_date');
            $table->json('original_values');
            $table->json('canonical_values');
            $table->char('raw_row_checksum', 64);
            $table->char('exact_canonical_hash', 64);
            $table->char('similarity_fingerprint', 64)->nullable();
            $table->string('normalization_policy_version')->default('field_specific_v1');
            $table->unsignedBigInteger('master_record_id')->nullable();
            $table->enum('row_status', [
                'new',
                'accepted',
                'duplicate_within_file',
                'historical_duplicate',
                'probable_duplicate',
                'invalid',
                'ignored',
            ])->default('new');
            $table->enum('duplicate_type', ['within_file', 'historical', 'probable'])->nullable();
            $table->unsignedBigInteger('duplicate_of_import_row_id')->nullable();
            $table->json('validation_errors')->nullable();
            $table->timestamps();

            $table->index(['import_id', 'source_row_number']);
            $table->index(['center_id', 'exact_canonical_hash']);
            $table->index('row_status');
        });

        Schema::table('import_rows', function (Blueprint $table) {
            $table->foreign('duplicate_of_import_row_id')
                ->references('id')
                ->on('import_rows')
                ->nullOnDelete();
        });

        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('import_verification_id')->nullable()->constrained('import_verifications')->cascadeOnDelete();
            $table->unsignedInteger('source_row_number')->nullable();
            $table->string('field')->nullable();
            $table->string('error_code');
            $table->text('original_value')->nullable();
            $table->text('raw_row')->nullable();
            $table->timestamps();

            $table->index('import_id');
            $table->index('import_verification_id');
            $table->index('error_code');
        });

        Schema::table('import_verifications', function (Blueprint $table) {
            $table->foreign('import_id')->references('id')->on('imports')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_verifications', function (Blueprint $table) {
            $table->dropForeign(['import_id']);
        });

        Schema::dropIfExists('import_errors');
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('imports');
    }
};
