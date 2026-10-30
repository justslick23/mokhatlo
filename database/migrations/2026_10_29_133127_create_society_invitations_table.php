
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('society_invitations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id')
                ->constrained('societies')
                ->restrictOnDelete();

            $table->string('email');
            $table->string('role_name', 40)->default('member');

            // Store only a hash, never the actual invitation token.
            $table->char('token_hash', 64)->unique();

            $table->foreignId('invited_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index(['society_id', 'email']);
            $table->index(['society_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('society_invitations');
    }
};
