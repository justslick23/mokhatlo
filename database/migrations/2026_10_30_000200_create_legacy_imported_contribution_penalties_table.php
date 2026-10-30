<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('legacy_imported_contribution_penalties')) {
            return;
        }

        Schema::create('legacy_imported_contribution_penalties', function (Blueprint $t) {
            $t->id();
            $t->foreignId('review_item_id');
            $t->foreign('review_item_id', 'licp_review_fk')
                ->references('id')->on('legacy_financial_review_items')->restrictOnDelete();
            $t->foreignId('society_id');
            $t->foreign('society_id', 'licp_soc_fk')
                ->references('id')->on('societies')->restrictOnDelete();
            $t->foreignId('contribution_obligation_id');
            $t->foreign('contribution_obligation_id', 'licp_obl_fk')
                ->references('id')->on('contribution_obligations')->restrictOnDelete();
            $t->foreignId('contribution_penalty_id');
            $t->foreign('contribution_penalty_id', 'licp_pen_fk')
                ->references('id')->on('contribution_penalties')->restrictOnDelete();
            $t->foreignId('contribution_penalty_payment_id');
            $t->foreign('contribution_penalty_payment_id', 'licp_pay_fk')
                ->references('id')->on('contribution_penalty_payments')->restrictOnDelete();
            $t->unsignedBigInteger('amount_cents');
            $t->date('original_paid_on');
            $t->string('evidence_reference', 255);
            $t->foreignId('imported_by');
            $t->foreign('imported_by', 'licp_actor_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $t->timestamp('imported_at');
            // Deliberately NULL: this patch does not post money to the ledger.
            $t->unsignedBigInteger('ledger_transaction_id')->nullable();
            $t->timestamps();
            $t->unique('review_item_id', 'licp_one_review');
            $t->unique('contribution_penalty_id', 'licp_one_penalty');
            $t->unique('contribution_penalty_payment_id', 'licp_one_payment');
            $t->index(['society_id', 'contribution_obligation_id'], 'licp_soc_obl_idx');
        });
    }

    public function down(): void
    {
        // Never silently erase source mappings for recognized financial records.
        // Reversal requires an independently reviewed accounting procedure.
    }
};
