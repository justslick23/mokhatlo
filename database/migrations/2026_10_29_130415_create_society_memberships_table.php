<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('society_memberships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('member_number', 40)->nullable();

            $table->string('status', 24)
                ->default('active');

            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();

            $table->timestamps();

            $table->unique(['society_id', 'user_id']);

            $table->unique([
                'society_id',
                'member_number'
            ]);

            $table->index([
                'society_id',
                'status'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('society_memberships');
    }
};