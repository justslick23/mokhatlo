
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_payout_drafts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('society_id')
                ->constrained('societies')
                ->restrictOnDelete();

            $table->foreignId('cycle_id')
                ->constrained('cycles')
                ->restrictOnDelete();

            $table->unsignedInteger('revision');

            $table->date('as_of_date');

            $table->string('status', 24)->default('draft');

            $table->string('distribution_method', 32);

            // Every monetary total is stored in integer cents.
            $table->unsignedBigInteger('savings_total_cents')
                ->default(0);

            $table->unsignedBigInteger('interest_total_cents')
                ->default(0);

            $table->unsignedBigInteger('penalty_pool_cents')
                ->default(0);

            $table->unsignedBigInteger('penalty_distributed_cents')
                ->default(0);

            $table->unsignedBigInteger('penalty_reserved_cents')
                ->default(0);

            $table->unsignedBigInteger('gross_total_cents')
                ->default(0);

            // Signed: an account may have a negative recorded balance.
            $table->bigInteger('liquid_assets_cents')
                ->default(0);

            $table->json('blockers');

            $table->char('source_hash', 64);

            $table->uuid('request_key');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(
                ['cycle_id', 'revision'],
                'cpd_cycle_revision_uq'
            );

            $table->unique(
                ['society_id', 'request_key'],
                'cpd_request_key_uq'
            );
        });

        Schema::create('cycle_payout_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cycle_payout_draft_id')
                ->constrained('cycle_payout_drafts')
                ->restrictOnDelete();

            $table->foreignId('society_id')
                ->constrained('societies')
                ->restrictOnDelete();

            $table->foreignId('society_membership_id')
                ->constrained('society_memberships')
                ->restrictOnDelete();

            // Preserve names as they were when draft was created.
            $table->string('member_name');
            $table->string('member_number')->nullable();
            $table->string('membership_status', 32);

            $table->unsignedBigInteger('savings_cents')
                ->default(0);

            $table->unsignedBigInteger('interest_refund_cents')
                ->default(0);

            $table->unsignedBigInteger('penalty_share_cents')
                ->default(0);

            $table->unsignedBigInteger('gross_payout_cents')
                ->default(0);

            $table->timestamps();

            $table->unique(
                ['cycle_payout_draft_id', 'society_membership_id'],
                'cpi_draft_member_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_payout_items');
        Schema::dropIfExists('cycle_payout_drafts');
    }
};
