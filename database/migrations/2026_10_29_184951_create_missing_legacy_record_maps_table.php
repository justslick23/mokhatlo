
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('legacy_record_maps')) {
            Schema::create('legacy_record_maps', function (Blueprint $table) {
                $table->id();

                $table->foreignId('source_id')
                    ->constrained('legacy_import_sources')
                    ->restrictOnDelete();

                $table->string('source_table', 64);
                $table->string('source_id_value', 128);
                $table->string('target_table', 64);
                $table->unsignedBigInteger('target_id');
                $table->char('source_row_sha256', 64)->nullable();

                $table->timestamps();

                $table->unique(
                    ['source_id', 'source_table', 'source_id_value'],
                    'legacy_record_natural_key'
                );

                $table->index(['target_table', 'target_id']);
            });
        }
    }

    public function down(): void
    {
        // Preserve historical import mappings for audit.
    }
};
