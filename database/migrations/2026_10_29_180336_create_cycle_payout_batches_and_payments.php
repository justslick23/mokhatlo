
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_payout_batches', function (Blueprint $table) {
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

            $table->foreignId('cycle_payout_approval_id')
                ->constrained('cycle_payout_approvals')
                ->restrictOnDelete();

            $table->string('status', 24)->default('processing');

            $table->char('approved_source_hash', 64);

            $table->unsignedBigInteger('gross_total_cents');

            $table->unsignedBigInteger('penalty_distributed_cents');

            $table->foreignId('penalty_journal_id')
                ->nullable()
                ->constrained('ledger_transactions')
                ->restrictOnDelete();

            $table->uuid('request_key');

            $table->foreignId('started_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('started_at');

            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->unique('cycle_id', 'cpb_one_per_cycle_uq');
            $table->unique('cycle_payout_draft_id', 'cpb_draft_uq');
            $table->unique('cycle_payout_approval_id', 'cpb_approval_uq');

            $table->unique(
                ['society_id', 'request_key'],
                'cpb_request_uq'
            );
        });

        Schema::create('cycle_payout_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id')
                ->constrained('societies')
                ->restrictOnDelete();

            $table->foreignId('cycle_payout_batch_id')
                ->constrained('cycle_payout_batches')
                ->restrictOnDelete();

            $table->foreignId('cycle_payout_item_id')
                ->constrained('cycle_payout_items')
                ->restrictOnDelete();

            $table->unsignedBigInteger('amount_cents');

            $table->unsignedBigInteger('savings_cents')
                ->default(0);

            $table->unsignedBigInteger('interest_cents')
                ->default(0);

            $table->unsignedBigInteger('penalty_cents')
                ->default(0);

            $table->date('paid_on');

            $table->string('payment_method', 32);
            $table->string('payment_reference', 120)->nullable();
            $table->text('notes')->nullable();

            $table->uuid('idempotency_key');

            $table->foreignId('ledger_transaction_id')
                ->nullable()
                ->constrained('ledger_transactions')
                ->restrictOnDelete();

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(
                ['society_id', 'idempotency_key'],
                'cpp_idempotency_uq'
            );

            $table->unique(
                'ledger_transaction_id',
                'cpp_ledger_uq'
            );

            $table->index(
                ['cycle_payout_batch_id', 'cycle_payout_item_id'],
                'cpp_batch_item_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_payout_payments');
        Schema::dropIfExists('cycle_payout_batches');
    }
};
