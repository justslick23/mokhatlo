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
        Schema::create('cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->string('name'); // e.g. "2025 Cycle"
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->timestamps();
        
            $table->unique(['society_id', 'status']); // only ONE active cycle
        });
    }
        

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cycles');
    }
};
