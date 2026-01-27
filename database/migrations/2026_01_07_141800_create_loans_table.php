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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained()->onDelete('cascade');
            $table->foreignId('society_id')->constrained()->onDelete('cascade');

            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->decimal('principal', 15, 2);
            $table->decimal('interest', 15, 2);
            $table->decimal('total_amount', 15, 2); // principal + interest
            $table->decimal('amount_repaid', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->date('issue_date');
            $table->date('due_date');
            $table->enum('status', ['active', 'repaid', 'overdue', 'written_off'])->default('active');
            $table->text('purpose')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('status');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
