<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->nullable();

            $table->unique(
                ['society_id', 'request_key'],
                'loans_request_unique'
            );

            $table->foreignId('society_id')
                ->constrained('societies')
                ->restrictOnDelete();

            $table->foreignId('cycle_id')
                ->constrained('cycles')
                ->restrictOnDelete();

            $table->foreignId('society_membership_id')
                ->constrained('society_memberships')
                ->restrictOnDelete();

            $table->string('loan_number', 40)->nullable();

            $table->decimal('principal_amount', 18, 2);

            $table->text('purpose');

            $table->unsignedSmallInteger('term_months');

            // Snapshot the policy at application time.
            $table->decimal('interest_rate_percent', 9, 5);

            $table->string('interest_method', 32)
                ->default('flat');

            $table->string('interest_basis', 24);

            $table->unsignedSmallInteger('policy_version')
                ->default(1);

            $table->decimal('quoted_interest_amount', 18, 2);

            $table->decimal('quoted_total_amount', 18, 2);

            $table->string('status', 32)
                ->default('pending');

            $table->timestamp('submitted_at');

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            $table->text('review_notes')->nullable();

            // Reserved for Phase 6B.
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('settled_at')->nullable();

            // For future historical data migration.
            $table->string('legacy_source', 60)->nullable();
            $table->string('legacy_id', 100)->nullable();

            $table->timestamps();

            $table->unique(
                ['society_id', 'loan_number'],
                'loans_society_number_uq'
            );

            $table->unique(
                ['id', 'society_id'],
                'loans_id_society_uq'
            );

            $table->unique(
                ['society_id', 'legacy_source', 'legacy_id'],
                'loans_legacy_import_uq'
            );

            $table->index(
                ['society_id', 'status'],
                'loans_society_status_idx'
            );

            $table->index(
                ['society_id', 'society_membership_id'],
                'loans_member_idx'
            );
        });

        Schema::create('loan_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id');

            $table->unsignedBigInteger('loan_id');

            $table->foreignId('actor_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('event_type', 40);

            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign(
                ['loan_id', 'society_id'],
                'loan_events_parent_fk'
            )
                ->references(['id', 'society_id'])
                ->on('loans')
                ->restrictOnDelete();

            $table->index(
                ['society_id', 'loan_id'],
                'loan_events_society_loan_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_events');
        Schema::dropIfExists('loans');
    }
};
