<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('society_mail_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->restrictOnDelete();
            $table->foreignId('cycle_id')->nullable();
            $table->string('kind', 48);
            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id');
            $table->date('occurred_on');
            $table->json('payload');
            $table->timestamps();
            $table->unique(['society_id', 'kind', 'source_type', 'source_id'], 'sme_source_uniq');
            $table->index(['society_id', 'occurred_on'], 'sme_soc_date_idx');
        });

        Schema::create('society_monthly_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->restrictOnDelete();
            $table->foreignId('cycle_id');
            $table->string('period', 7);
            $table->string('audience_key', 50); // all or member:<id>
            $table->json('payload'); // frozen at report generation, no silent changes
            $table->timestamps();
            $table->unique(['society_id', 'cycle_id', 'period', 'audience_key'], 'sms_month_aud_uniq');
        });

        Schema::create('society_mail_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('mail_event_id')->nullable();
            $table->unsignedBigInteger('monthly_snapshot_id')->nullable();
            $table->foreignId('society_membership_id');
            $table->foreignId('user_id');
            $table->string('recipient_email', 254);
            $table->char('delivery_key', 64)->unique();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at'], 'smd_status_next_idx');
            $table->index(['society_id', 'status'], 'smd_soc_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('society_mail_deliveries');
        Schema::dropIfExists('society_monthly_snapshots');
        Schema::dropIfExists('society_mail_events');
    }
};
