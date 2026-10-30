<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('societies', function (Blueprint $table) {
            $table->id();

            $table->string('name', 160);
            $table->text('description')->nullable();

            $table->char('currency_code', 3)->default('LSL');

            $table->string('timezone', 64)
                ->default('Africa/Maseru');

            $table->string('status', 24)
                ->default('active');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('societies');
    }
};