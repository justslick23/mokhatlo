<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('legacy_imported_loan_penalties')) {
            return;
        }

        Schema::create('legacy_imported_loan_penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_item_id');
            $table->foreign('review_item_id', 'lilp_review_fk')
                ->references('id')->on('legacy_financial_review_items')->restrictOnDelete();
            $table->foreignId('society_id');
            $table->foreign('society_id', 'lilp_society_fk')
                ->references('id')->on('societies')->restrictOnDelete();
            $table->foreignId('loan_id');
            $table->foreign('loan_id', 'lilp_loan_fk')
                ->references('id')->on('loans')->restrictOnDelete();
            $table->foreignId('loan_penalty_id');
            $table->foreign('loan_penalty_id', 'lilp_penalty_fk')
                ->references('id')->on('loan_penalties')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('evidence_reference', 255);
            $table->foreignId('imported_by');
            $table->foreign('imported_by', 'lilp_actor_fk')
                ->references('id')->on('users')->restrictOnDelete();
            $table->timestamp('imported_at');
            $table->timestamps();
            $table->unique('review_item_id', 'lilp_one_review');
            $table->unique('loan_penalty_id', 'lilp_one_penalty');
            $table->index(['society_id', 'loan_id'], 'lilp_soc_loan_idx');
        });
    }

    public function down(): void
    {
        // A migration rollback must never silently unlink financially assessed
        // penalties from their source review and evidence. Requires explicit reversal.
    }
};
