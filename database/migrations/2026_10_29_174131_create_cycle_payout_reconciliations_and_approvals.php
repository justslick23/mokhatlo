<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_payout_reconciliations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id')
                ->constrained('societies')
                ->restrictOnDelete();

            $table->foreignId('cycle_payout_draft_id')
                ->constrained('cycle_payout_drafts')
                ->restrictOnDelete();

            $table->date('reconciled_on');

            // Recorded ledger balances, in cents.
            $table->bigInteger('ledger_cash_cents');
            $table->bigInteger('ledger_bank_cents');
            $table->bigInteger('ledger_mobile_money_cents');

            // Independently confirmed balances, in cents.
            $table->bigInteger('confirmed_cash_cents');
            $table->bigInteger('confirmed_bank_cents');
            $table->bigInteger('confirmed_mobile_money_cents');

            $table->text('cash_count_notes')->nullable();
            $table->string('bank_statement_reference', 160)->nullable();
            $table->string('mobile_money_reference', 160)->nullable();

            $table->text('notes')->nullable();

            $table->char('source_hash', 64);

            $table->uuid('request_key');

            $table->foreignId('confirmed_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(
                'cycle_payout_draft_id',
                'cpr_one_per_draft_uq'
            );

            $table->unique(
                ['society_id', 'request_key'],
                'cpr_request_key_uq'
            );
        });

        Schema::create('cycle_payout_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id')
                ->constrained('societies')
                ->restrictOnDelete();

            $table->foreignId('cycle_id')
                ->constrained('cycles')
                ->restrictOnDelete();

            $table->foreignId('cycle_payout_draft_id')
                ->constrained('cycle_payout_drafts')
                ->restrictOnDelete();

            $table->foreignId('cycle_payout_reconciliation_id')
                ->constrained('cycle_payout_reconciliations')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('approved_at');

            $table->text('approval_notes');

            $table->char('approved_source_hash', 64);

            $table->uuid('request_key');

            $table->timestamps();

            // A cycle may have only one approved payout.
            $table->unique('cycle_id', 'cpa_one_per_cycle_uq');

            $table->unique(
                'cycle_payout_draft_id',
                'cpa_one_per_draft_uq'
            );

            $table->unique(
                ['society_id', 'request_key'],
                'cpa_request_key_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_payout_approvals');
        Schema::dropIfExists('cycle_payout_reconciliations');
    }
};
