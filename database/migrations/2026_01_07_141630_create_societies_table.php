<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('societies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('minimum_contribution', 15, 2)->default(0);
            $table->decimal('interest_rate', 5, 2)->default(10.00); // Percentage
            $table->enum('penalty_type', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('penalty_value', 10, 2)->default(0);
            $table->integer('contribution_due_day')->default(1); // Day of month
            $table->integer('repayment_period_months')->default(12);
            $table->string('bank_account_reference')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('societies');
    }
};
