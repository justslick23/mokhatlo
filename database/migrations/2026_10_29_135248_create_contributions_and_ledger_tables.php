<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        | Ledger Accounts
        */
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('type', 30);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['society_id', 'code']);
        });

        /*
        | Monthly Contribution Obligations
        */
        Schema::create('contribution_obligations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('cycle_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('society_membership_id')
                ->constrained('society_memberships')
                ->restrictOnDelete();

            $table->date('period_start');
            $table->date('due_date');

            $table->decimal('required_amount', 18, 2);

            $table->timestamps();

            $table->unique(
                [
                    'cycle_id',
                    'society_membership_id',
                    'period_start'
                ],
                'contribution_obligation_unique'
            );

            $table->index(
                [
                    'society_id',
                    'cycle_id',
                    'period_start'
                ],
                'contrib_obligation_cycle_idx'
            );
        });

        /*
        | Actual Contribution Payments
        */
        Schema::create('contribution_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('cycle_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('society_membership_id')
                ->constrained('society_memberships')
                ->restrictOnDelete();

            $table->foreignId('contribution_obligation_id')
                ->constrained('contribution_obligations')
                ->restrictOnDelete();

            $table->decimal('amount', 18, 2);

            $table->date('paid_on');

            $table->string('payment_method', 32);
            $table->string('payment_reference', 120)->nullable();

            $table->text('notes')->nullable();

            $table->uuid('idempotency_key');

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(
                ['society_id', 'idempotency_key'],
                'contribution_payment_idempotency'
            );

         
           
$table->index(
    [
        'society_id',
        'society_membership_id',
        'paid_on'
    ],
    'contrib_pay_member_date_idx'
);


        });

        /*
        | Ledger Transaction Headers
        */
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('cycle_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();

            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id');

            $table->date('posting_date');
            $table->string('description', 255);

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(
                ['society_id', 'source_type', 'source_id'],
                'ledger_unique_source'
            );
        });

        /*
        | Ledger Debit / Credit Lines
        */
        Schema::create('ledger_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ledger_transaction_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('ledger_account_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('debit_amount', 18, 2)->default(0);
            $table->decimal('credit_amount', 18, 2)->default(0);

            $table->timestamps();

            $table->index('ledger_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_lines');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('contribution_payments');
        Schema::dropIfExists('contribution_obligations');
        Schema::dropIfExists('ledger_accounts');
    }
};
