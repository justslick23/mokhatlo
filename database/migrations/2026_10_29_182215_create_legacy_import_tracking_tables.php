
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Original legacy database/source.
         */
        if (! Schema::hasTable('legacy_import_sources')) {
            Schema::create('legacy_import_sources', function (Blueprint $table) {
                $table->id();

                $table->string('source_code', 100)->unique();
                $table->text('description')->nullable();

                $table->timestamps();
            });
        }

        /*
         * Every snapshot/import attempt receives a batch.
         */
        if (! Schema::hasTable('legacy_import_batches')) {
            Schema::create('legacy_import_batches', function (Blueprint $table) {
                $table->id();

                $table->foreignId('source_id')
                    ->constrained('legacy_import_sources')
                    ->restrictOnDelete();

                $table->char('file_sha256', 64);

                $table->string('mode', 30)
                    ->default('dry_run');

                $table->string('status', 30)
                    ->default('pending');

                $table->json('counts')->nullable();

                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();

                $table->timestamps();

                $table->index(['source_id', 'status']);
                $table->index('file_sha256');
            });
        }

        /*
         * Problems discovered while reviewing old data.
         */
        if (! Schema::hasTable('legacy_import_exceptions')) {
            Schema::create('legacy_import_exceptions', function (Blueprint $table) {
                $table->id();

                $table->foreignId('batch_id')
                    ->constrained('legacy_import_batches')
                    ->restrictOnDelete();

                $table->string('source_table', 100);

                $table->string('source_id_value', 128)
                    ->nullable();

                $table->string('severity', 20);
                $table->string('error_code', 100);

                $table->text('details');

                $table->string('resolution_status', 30)
                    ->default('unresolved');

                $table->timestamps();

                $table->index(['batch_id', 'severity']);
                $table->index([
                    'source_table',
                    'source_id_value',
                ], 'legacy_exception_source_idx');
            });
        }
    }

    public function down(): void
    {
        /*
         * Intentionally preserve legacy import tracking data.
         * These tables may contain financial audit evidence.
         *
         * Removal must be performed through a separately
         * reviewed migration after a database backup.
         */
    }
};
