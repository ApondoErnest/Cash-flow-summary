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
        Schema::create('csv_format_versions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('version');
            $table->unsignedTinyInteger('column_count')->default(10);
            $table->char('delimiter', 1)->default(';');
            $table->string('encoding')->default('UTF-8');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('header_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('csv_format_version_id')->constrained()->cascadeOnDelete();
            $table->string('canonical_field');
            $table->enum('language', ['fr', 'en']);
            $table->string('source_header');
            $table->string('normalized_header');
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['csv_format_version_id', 'language', 'source_header'], 'header_aliases_version_language_source_unique');
            $table->index(['csv_format_version_id', 'language', 'is_active'], 'header_aliases_version_language_active_index');
            $table->index(['csv_format_version_id', 'canonical_field'], 'header_aliases_version_canonical_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('header_aliases');
        Schema::dropIfExists('csv_format_versions');
    }
};
