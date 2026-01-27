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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained()->onDelete('cascade');
            $table->foreignId('society_id')->constrained()->onDelete('cascade');

            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->foreignId('loan_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('type', [
                'contribution',
                'loan_issued',
                'loan_repayment',
                'interest',
                'penalty',
                'year_end_payout'
            ]);
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for better query performance
            $table->index('transaction_date');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
