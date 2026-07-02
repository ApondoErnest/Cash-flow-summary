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
        Schema::create('import_day_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('center_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->enum('comparison_result', [
                'new',
                'unchanged',
                'revision_required',
                'covered_without_rows',
                'invalid',
            ]);
            $table->unsignedBigInteger('existing_version_id')->nullable();
            $table->unsignedBigInteger('proposed_version_id')->nullable();
            $table->decimal('existing_ht', 15, 2)->nullable();
            $table->decimal('existing_vat', 15, 2)->nullable();
            $table->decimal('existing_ttc', 15, 2)->nullable();
            $table->decimal('proposed_ht', 15, 2)->nullable();
            $table->decimal('proposed_vat', 15, 2)->nullable();
            $table->decimal('proposed_ttc', 15, 2)->nullable();
            $table->integer('record_count_delta')->nullable();
            $table->timestamps();

            $table->unique(['import_id', 'business_date']);
            $table->index(['center_id', 'business_date']);
            $table->index('comparison_result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_day_comparisons');
    }
};
