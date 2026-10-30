
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // Snapshot for new applications.
            // Nullable for existing / imported loans.
            $table->decimal(
                'loan_penalty_rate_percent',
                9,
                5
            )->nullable();
        });

        Schema::create('loan_penalties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id');
            $table->unsignedBigInteger('loan_id');
            $table->unsignedBigInteger('loan_installment_id');

            $table->decimal('base_amount', 18, 2);
            $table->decimal('rate_percent', 9, 5);
            $table->decimal('assessed_amount', 18, 2);

            $table->date('cutoff_date');
            $table->date('assessed_on');

            $table->string('calculation_method', 40)
                ->default('one_time_unpaid_installment');

            $table->timestamps();

            $table->foreign(
                ['loan_id', 'society_id'],
                'lp_loan_society_fk'
            )
                ->references(['id', 'society_id'])
                ->on('loans')
                ->restrictOnDelete();

            $table->foreign(
                ['loan_installment_id', 'loan_id'],
                'lp_installment_loan_fk'
            )
                ->references(['id', 'loan_id'])
                ->on('loan_installments')
                ->restrictOnDelete();

            $table->unique(
                'loan_installment_id',
                'lp_installment_unique'
            );

            $table->unique(
                ['id', 'society_id'],
                'lp_id_society_unique'
            );

            $table->index(
                ['society_id', 'loan_id'],
                'lp_society_loan_idx'
            );
        });

        Schema::create('loan_penalty_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id');
            $table->unsignedBigInteger('loan_penalty_id');

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
                ['loan_penalty_id', 'society_id'],
                'lpp_penalty_society_fk'
            )
                ->references(['id', 'society_id'])
                ->on('loan_penalties')
                ->restrictOnDelete();

            $table->unique(
                ['society_id', 'idempotency_key'],
                'lpp_idempotency_unique'
            );
        });

        Schema::create('loan_penalty_waivers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id');
            $table->unsignedBigInteger('loan_penalty_id');

            $table->decimal('amount', 18, 2);
            $table->text('reason');

            $table->uuid('idempotency_key');

            $table->foreignId('waived_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->foreign(
                ['loan_penalty_id', 'society_id'],
                'lpw_penalty_society_fk'
            )
                ->references(['id', 'society_id'])
                ->on('loan_penalties')
                ->restrictOnDelete();

            $table->unique(
                ['society_id', 'idempotency_key'],
                'lpw_idempotency_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_penalty_waivers');
        Schema::dropIfExists('loan_penalty_payments');
        Schema::dropIfExists('loan_penalties');

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('loan_penalty_rate_percent');
        });
    }
};
