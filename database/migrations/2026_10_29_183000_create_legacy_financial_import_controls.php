<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('legacy_financial_import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('legacy_import_batches')->restrictOnDelete();
            $table->foreignId('society_id')->constrained('societies')->restrictOnDelete();
            $table->foreignId('cycle_id')->constrained('cycles')->restrictOnDelete();
            $table->foreignId('imported_by')->constrained('users')->restrictOnDelete();
            $table->char('source_fingerprint', 64);
            $table->char('plan_sha256', 64);
            $table->json('posted_totals');
            $table->timestamp('imported_at');
            $table->timestamps();
            $table->unique(['society_id', 'cycle_id']);
            $table->unique(['batch_id', 'society_id']);
        });

        Schema::create('legacy_financial_review_items', function (Blueprint $table) {
            $table->id();
$table->foreignId('legacy_financial_import_run_id');

$table->foreign(
    'legacy_financial_import_run_id',
    'lfr_import_run_fk'
)
    ->references('id')
    ->on('legacy_financial_import_runs')
    ->restrictOnDelete();
	$table->string('source_table', 64);
            $table->string('source_id_value', 128);
            $table->string('reason', 64);
            $table->bigInteger('amount_cents');
            $table->foreignId('loan_id')->nullable()->constrained('loans')->restrictOnDelete();
            $table->foreignId('society_membership_id')->nullable()->constrained('society_memberships')->restrictOnDelete();
            $table->string('resolution_status', 20)->default('pending');
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            $table->unique(['legacy_financial_import_run_id','source_table','source_id_value','reason'], 'lfr_unique_review');
        });
    }

    public function down(): void
    {
        // Financial imports and their audit evidence must not disappear on rollback.
        // Deliberately do not drop the tables. A documented reversal process is required.
    }
};
