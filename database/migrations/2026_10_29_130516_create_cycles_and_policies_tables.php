<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('name', 160);
            $table->string('code', 32);

            $table->date('start_date');
            $table->date('end_date');

            $table->string('status', 24)->default('draft');

            $table->unsignedTinyInteger('active_slot')
                ->nullable();

            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->unique(['society_id', 'code']);
            $table->unique(['society_id', 'active_slot']);
            $table->unique(['id', 'society_id']);

            $table->index(['society_id', 'status']);
        });

        Schema::create('cycle_policies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('cycle_id');

            $table->unsignedSmallInteger('version')->default(1);

            $table->decimal(
                'minimum_monthly_contribution',
                18,
                2
            );

            $table->unsignedTinyInteger(
                'contribution_due_day'
            );

            $table->unsignedTinyInteger(
                'contribution_grace_days'
            )->default(0);

            $table->decimal(
                'contribution_penalty_percent',
                9,
                5
            )->default(0);

            $table->decimal(
                'loan_interest_percent',
                9,
                5
            )->default(0);

            $table->string(
                'loan_interest_method',
                32
            )->default('flat');

            $table->string(
                'loan_interest_basis',
                24
            )->default('per_term');

            $table->unsignedSmallInteger(
                'loan_max_term_months'
            )->default(12);

            $table->decimal(
                'loan_penalty_percent',
                9,
                5
            )->default(0);

            $table->timestamps();

            $table->unique('cycle_id');

            $table->foreign(['cycle_id', 'society_id'])
                ->references(['id', 'society_id'])
                ->on('cycles')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_policies');
        Schema::dropIfExists('cycles');
    }
};