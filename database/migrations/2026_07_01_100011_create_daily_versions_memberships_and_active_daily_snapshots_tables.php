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
        Schema::create('daily_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->foreignId('import_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version_number');
            $table->char('dataset_hash', 64);
            $table->unsignedInteger('record_count');
            $table->decimal('total_ht', 15, 2);
            $table->decimal('total_vat', 15, 2);
            $table->decimal('total_ttc', 15, 2);
            $table->enum('status', [
                'proposed',
                'active',
                'superseded',
                'rejected',
                'invalid',
            ])->default('proposed');
            $table->unsignedBigInteger('previous_version_id')->nullable();
            $table->text('revision_reason')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();

            $table->unique(['center_id', 'business_date', 'version_number']);
            $table->index(['center_id', 'business_date']);
            $table->index('status');
        });

        Schema::table('daily_versions', function (Blueprint $table) {
            $table->foreign('previous_version_id')
                ->references('id')
                ->on('daily_versions')
                ->nullOnDelete();
        });

        Schema::create('daily_version_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('master_cash_flow_record_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['daily_version_id', 'master_cash_flow_record_id'], 'daily_version_membership_unique');
        });

        Schema::create('active_daily_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->foreignId('daily_version_id')->constrained()->restrictOnDelete();
            $table->timestamp('activated_at');
            $table->timestamps();

            $table->unique(['center_id', 'business_date']);
        });

        Schema::table('import_day_comparisons', function (Blueprint $table) {
            $table->foreign('existing_version_id')
                ->references('id')
                ->on('daily_versions')
                ->nullOnDelete();

            $table->foreign('proposed_version_id')
                ->references('id')
                ->on('daily_versions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_day_comparisons', function (Blueprint $table) {
            $table->dropForeign(['existing_version_id']);
            $table->dropForeign(['proposed_version_id']);
        });

        Schema::dropIfExists('active_daily_snapshots');
        Schema::dropIfExists('daily_version_memberships');
        Schema::table('daily_versions', function (Blueprint $table) {
            $table->dropForeign(['previous_version_id']);
        });
        Schema::dropIfExists('daily_versions');
    }
};
