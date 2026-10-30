<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_penalties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id')
                ->constrained('societies')
                ->restrictOnDelete();

            $table->foreignId('cycle_id')
                ->constrained('cycles')
                ->restrictOnDelete();

            $table->foreignId('contribution_obligation_id')
                ->constrained('contribution_obligations')
                ->restrictOnDelete();

            $table->decimal('base_amount', 18, 2);
            $table->decimal('rate_percent', 9, 5);
            $table->decimal('assessed_amount', 18, 2);

            $table->date('cutoff_date');
            $table->date('assessed_on');

            $table->string('calculation_method', 32)
                ->default('one_time_unpaid_minimum');

            $table->timestamps();

            $table->unique(
                'contribution_obligation_id',
                'cp_obligation_unique'
            );

            $table->unique(
                ['id', 'society_id'],
                'cp_id_society_unique'
            );

            $table->index(
                ['society_id', 'cycle_id'],
                'cp_society_cycle_idx'
            );
        });

        Schema::create(
            'contribution_penalty_payments',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('society_id');

                $table->unsignedBigInteger(
                    'contribution_penalty_id'
                );

                $table->decimal('amount', 18, 2);
                $table->date('paid_on');

                $table->string('payment_method', 32);
                $table->string('payment_reference', 120)
                    ->nullable();

                $table->text('notes')->nullable();

                $table->uuid('idempotency_key');

                $table->foreignId('recorded_by')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamps();

                $table->foreign(
                    ['contribution_penalty_id', 'society_id'],
                    'cpp_parent_fk'
                )
                    ->references(['id', 'society_id'])
                    ->on('contribution_penalties')
                    ->restrictOnDelete();

                $table->unique(
                    ['society_id', 'idempotency_key'],
                    'cpp_idempotency_unique'
                );

                $table->index(
                    ['society_id', 'paid_on'],
                    'cpp_society_date_idx'
                );
            }
        );

        Schema::create(
            'contribution_penalty_waivers',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('society_id');

                $table->unsignedBigInteger(
                    'contribution_penalty_id'
                );

                $table->decimal('amount', 18, 2);
                $table->text('reason');

                $table->uuid('idempotency_key');

                $table->foreignId('waived_by')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamps();

                $table->foreign(
                    ['contribution_penalty_id', 'society_id'],
                    'cpw_parent_fk'
                )
                    ->references(['id', 'society_id'])
                    ->on('contribution_penalties')
                    ->restrictOnDelete();

                $table->unique(
                    ['society_id', 'idempotency_key'],
                    'cpw_idempotency_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_penalty_waivers');
        Schema::dropIfExists('contribution_penalty_payments');
        Schema::dropIfExists('contribution_penalties');
    }
};
