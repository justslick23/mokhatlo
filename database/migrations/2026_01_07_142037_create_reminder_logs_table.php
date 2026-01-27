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
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->foreignId('loan_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('type', [
                'contribution_due',
                'loan_repayment_due',
                'loan_overdue'
            ]);
            $table->date('reminder_date');
            $table->boolean('sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            
            // Prevent duplicate reminders
            $table->unique(['member_id', 'type', 'reminder_date', 'loan_id']);
            
            // Indexes
            $table->index('reminder_date');
            $table->index('sent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
    }
};
