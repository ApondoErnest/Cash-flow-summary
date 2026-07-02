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
        Schema::create('master_cash_flow_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained()->cascadeOnDelete();
            $table->date('registration_date');
            $table->time('registration_time');
            $table->date('completion_date')->nullable();
            $table->string('customer_name');
            $table->string('customer_name_normalized');
            $table->string('category_code');
            $table->string('inspection_type_code');
            $table->string('licence_plate');
            $table->string('licence_plate_normalized');
            $table->decimal('net_amount', 15, 2);
            $table->decimal('vat_amount', 15, 2);
            $table->decimal('gross_amount', 15, 2);
            $table->enum('completion_status', ['completed', 'unfinished']);
            $table->enum('financial_status', ['revenue', 'zero_value']);
            $table->char('exact_canonical_hash', 64);
            $table->string('normalization_policy_version')->default('field_specific_v1');
            $table->foreignId('first_import_id')->constrained('imports')->restrictOnDelete();
            $table->foreignId('first_import_row_id')->constrained('import_rows')->restrictOnDelete();
            $table->timestamp('first_seen_at');
            $table->timestamps();

            $table->unique(
                ['center_id', 'normalization_policy_version', 'exact_canonical_hash'],
                'master_records_center_policy_hash_unique',
            );
            $table->index(['center_id', 'registration_date']);
            $table->index(['center_id', 'exact_canonical_hash']);
            $table->index('licence_plate_normalized');
            $table->index('customer_name_normalized');
        });

        Schema::table('import_rows', function (Blueprint $table) {
            $table->foreign('master_record_id')
                ->references('id')
                ->on('master_cash_flow_records')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            $table->dropForeign(['master_record_id']);
        });

        Schema::dropIfExists('master_cash_flow_records');
    }
};
