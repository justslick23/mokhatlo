
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_import_staged_rows', function (Blueprint $table) {
            $table->id();

            $table->foreignId('batch_id')
                ->constrained('legacy_import_batches')
                ->restrictOnDelete();

            $table->string('source_table', 64);

            $table->string('source_id_value', 128);

            $table->string('event_kind', 64)->nullable();

            $table->char('source_row_sha256', 64);

            /*
             * Store historical source data encrypted with
             * Laravel's application encryption key.
             */
            $table->longText('payload_encrypted');

            $table->timestamps();

            $table->unique(
                [
                    'batch_id',
                    'source_table',
                    'source_id_value',
                ],
                'legacy_staged_natural_key'
            );

            $table->index([
                'source_table',
                'event_kind',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_import_staged_rows');
    }
};
