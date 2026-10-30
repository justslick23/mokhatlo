<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('legacy_reviewed_contribution_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained('societies')->restrictOnDelete();
            $table->foreignId('cycle_id')->constrained('cycles')->restrictOnDelete();
            $table->foreignId('contribution_obligation_id');
            $table->foreign('contribution_obligation_id', 'lrco_obligation_fk')
                ->references('id')->on('contribution_obligations')->restrictOnDelete();
            $table->string('evidence_reference', 255);
            $table->unsignedBigInteger('verified_required_cents');
            $table->date('verified_due_date');
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamps();
            $table->unique('contribution_obligation_id', 'lrco_obligation_unique');
        });

        Schema::create('legacy_receipt_obligation_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained('societies')->restrictOnDelete();
            $table->foreignId('cycle_id')->constrained('cycles')->restrictOnDelete();
            $table->foreignId('contribution_payment_id');
            $table->foreign('contribution_payment_id', 'lrol_payment_fk')
                ->references('id')->on('contribution_payments')->restrictOnDelete();
            $table->foreignId('legacy_receipt_period_attribution_id');
            $table->foreign('legacy_receipt_period_attribution_id', 'lrol_attribution_fk')
                ->references('id')->on('legacy_receipt_period_attributions')->restrictOnDelete();
            $table->foreignId('contribution_obligation_id');
            $table->foreign('contribution_obligation_id', 'lrol_obligation_fk')
                ->references('id')->on('contribution_obligations')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('evidence_reference', 255);
            $table->foreignId('linked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('linked_at');
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
            $table->index(['legacy_receipt_period_attribution_id', 'reversed_at'], 'lrol_attr_active_idx');
            $table->index(['society_id', 'cycle_id', 'contribution_payment_id'], 'lrol_receipt_cycle_idx');
            $table->index(['contribution_obligation_id', 'reversed_at'], 'lrol_obligation_idx');
        });
    }

    public function down(): void
    {
        // Historical classification evidence is append-only; retain on ordinary rollback.
        // A separately reviewed archival migration is required for removal.
    }
};
