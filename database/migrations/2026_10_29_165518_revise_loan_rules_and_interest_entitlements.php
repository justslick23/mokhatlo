<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycle_policies', function (Blueprint $table) {
            $table->unsignedSmallInteger('loan_maturity_months')
                ->default(2);

            $table->string('penalty_distribution_method', 32)
                ->default('equal_all');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('lender_membership_id')
                ->nullable()
                ->constrained('society_memberships')
                ->restrictOnDelete();

            $table->date('matures_on')->nullable();
        });

        /*
         * Existing instalment penalties are retained.
         * New whole-loan penalties have no installment ID.
         */
        Schema::table('loan_penalties', function (Blueprint $table) {
            $table->unsignedBigInteger('loan_installment_id')
                ->nullable()
                ->change();

            $table->string('assessment_scope', 24)
                ->default('installment');

            $table->unsignedTinyInteger('maturity_slot')
                ->nullable();

            $table->unique(
                ['loan_id', 'maturity_slot'],
                'lp_maturity_once_uq'
            );
        });

        /*
         * Interest collected on a loan is attributed to
         * the lender and reserved for their future payout.
         */
        Schema::create('loan_interest_credits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('loan_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('loan_repayment_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('beneficiary_membership_id')
                ->constrained('society_memberships')
                ->restrictOnDelete();

            $table->decimal('amount', 18, 2);

            $table->timestamps();

            $table->unique(
                'loan_repayment_id',
                'lic_repayment_once_uq'
            );

            $table->index(
                ['society_id', 'beneficiary_membership_id'],
                'lic_member_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_interest_credits');

        Schema::table('loan_penalties', function (Blueprint $table) {
            $table->dropUnique('lp_maturity_once_uq');
            $table->dropColumn([
                'assessment_scope',
                'maturity_slot',
            ]);

            // Only safe if no new loan-level penalty
            // contains a NULL installment ID.
            $table->unsignedBigInteger('loan_installment_id')
                ->nullable(false)
                ->change();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lender_membership_id');
            $table->dropColumn('matures_on');
        });

        Schema::table('cycle_policies', function (Blueprint $table) {
            $table->dropColumn([
                'loan_maturity_months',
                'penalty_distribution_method',
            ]);
        });
    }
};
