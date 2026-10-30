<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_disbursements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id');
            $table->unsignedBigInteger('loan_id');

            $table->decimal('amount', 18, 2);
            $table->date('disbursed_on');
            $table->string('payment_method', 32);
            $table->string('payment_reference', 120)->nullable();
            $table->text('notes')->nullable();

            $table->uuid('idempotency_key');

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->foreign(
                ['loan_id', 'society_id'],
                'ld_loan_society_fk'
            )
                ->references(['id', 'society_id'])
                ->on('loans')
                ->restrictOnDelete();

            // One disbursement per loan in this release.
            $table->unique('loan_id', 'ld_loan_unique');

            $table->unique(
                ['society_id', 'idempotency_key'],
                'ld_idempotency_unique'
            );
        });

        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id');
            $table->unsignedBigInteger('loan_id');

            $table->unsignedSmallInteger('installment_number');
            $table->date('due_date');

            $table->decimal('principal_due', 18, 2);
            $table->decimal('interest_due', 18, 2);
            $table->decimal('total_due', 18, 2);

            $table->timestamps();

            $table->foreign(
                ['loan_id', 'society_id'],
                'li_loan_society_fk'
            )
                ->references(['id', 'society_id'])
                ->on('loans')
                ->restrictOnDelete();

            $table->unique(
                ['loan_id', 'installment_number'],
                'li_loan_number_unique'
            );

            $table->unique(
                ['id', 'loan_id'],
                'li_id_loan_unique'
            );
        });

        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id');
            $table->unsignedBigInteger('loan_id');

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

            $table->foreign(
                ['loan_id', 'society_id'],
                'lr_loan_society_fk'
            )
                ->references(['id', 'society_id'])
                ->on('loans')
                ->restrictOnDelete();

            $table->unique(
                ['society_id', 'idempotency_key'],
                'lr_idempotency_unique'
            );

            $table->unique(
                ['id', 'loan_id'],
                'lr_id_loan_unique'
            );

            $table->index(
                ['society_id', 'paid_on'],
                'lr_society_date_idx'
            );
        });

        Schema::create('loan_repayment_allocations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('loan_id');
            $table->unsignedBigInteger('loan_repayment_id');
            $table->unsignedBigInteger('loan_installment_id');

            $table->decimal('principal_amount', 18, 2)
                ->default(0);

            $table->decimal('interest_amount', 18, 2)
                ->default(0);

            $table->timestamps();

            $table->foreign(
                ['loan_repayment_id', 'loan_id'],
                'lra_repayment_fk'
            )
                ->references(['id', 'loan_id'])
                ->on('loan_repayments')
                ->restrictOnDelete();

            $table->foreign(
                ['loan_installment_id', 'loan_id'],
                'lra_installment_fk'
            )
                ->references(['id', 'loan_id'])
                ->on('loan_installments')
                ->restrictOnDelete();

            $table->unique(
                ['loan_repayment_id', 'loan_installment_id'],
                'lra_pair_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayment_allocations');
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('loan_installments');
        Schema::dropIfExists('loan_disbursements');
    }
};
