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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('society_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['chairman', 'treasurer', 'secretary', 'member'])->default('member');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->date('joined_date');
            $table->timestamps();
            $table->softDeletes();
            
            // Ensure unique user-society combination
            $table->unique(['user_id', 'society_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
