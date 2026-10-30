<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('legacy_receipt_period_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained('societies')->restrictOnDelete();
            $table->foreignId('cycle_id')->constrained('cycles')->restrictOnDelete();
            $table->foreignId('contribution_payment_id');
            $table->foreign('contribution_payment_id', 'lrpa_payment_fk')
                ->references('id')->on('contribution_payments')->restrictOnDelete();
            $table->date('period_start');
            $table->unsignedBigInteger('amount_cents');
            $table->string('evidence_reference', 255);
            $table->text('notes')->nullable();
            $table->foreignId('allocated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('allocated_at');
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
            $table->index(['contribution_payment_id', 'reversed_at'], 'lrpa_receipt_active_idx');
            $table->index(['society_id', 'cycle_id', 'period_start'], 'lrpa_cycle_period_idx');
        });
    }

    public function down(): void
    {
        // Preserve audit evidence. Any removal requires a reviewed archival/reversal plan.
    }
};
