<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('legacy_penalty_review_actions')) {
            Schema::create('legacy_penalty_review_actions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('review_item_id');
                $t->foreign('review_item_id', 'lpra_item_fk')
                    ->references('id')->on('legacy_financial_review_items')->restrictOnDelete();
                $t->string('decision', 32);
                $t->string('evidence_reference', 255);
                $t->text('notes');
                $t->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
                $t->timestamps();
                $t->index(['review_item_id', 'created_at'], 'lpra_item_time_idx');
            });
        }
        if (! Schema::hasTable('legacy_clearing_reconciliations')) {
            Schema::create('legacy_clearing_reconciliations', function (Blueprint $t) {
                $t->id();
                $t->foreignId('import_run_id');
                $t->foreign('import_run_id', 'lcr_run_fk')
                    ->references('id')->on('legacy_financial_import_runs')->restrictOnDelete();
                $t->foreignId('society_id')->constrained('societies')->restrictOnDelete();
                $t->unsignedBigInteger('expected_cents');
                $t->unsignedBigInteger('cash_cents')->default(0);
                $t->unsignedBigInteger('bank_cents')->default(0);
                $t->unsignedBigInteger('mobile_cents')->default(0);
                $t->string('evidence_reference', 255);
                $t->text('notes');
                $t->string('status', 24)->default('prepared');
                $t->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
                $t->timestamp('prepared_at');
                $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
                $t->timestamp('approved_at')->nullable();
                $t->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
                $t->timestamp('posted_at')->nullable();
                $t->unsignedBigInteger('ledger_transaction_id')->nullable();
                $t->foreign('ledger_transaction_id', 'lcr_ledger_fk')
                    ->references('id')->on('ledger_transactions')->restrictOnDelete();
                $t->timestamps();
                $t->unique('import_run_id', 'lcr_run_unique');
            });
        }
    }
    public function down(): void
    {
        // Audit evidence and posted reconciliations must not be erased on rollback.
    }
};
