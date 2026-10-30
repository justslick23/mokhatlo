<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\Society;
use App\Models\User;
use App\Services\Contributions\GenerateContributionObligations;
use App\Services\Societies\CreateSociety;
use Database\Seeders\ContributionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContributionTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveCycle(Society $society): Cycle
    {
        
$society->memberships()->update([
    'joined_at' => '2026-12-01 00:00:00',
]);

        $cycle = $society->cycles()->create([
            'name' => '2027 Savings',
            'code' => '2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'status' => 'active',
            'active_slot' => 1,
            'activated_at' => now(),
        ]);

        $cycle->policy()->create([
            'society_id' => $society->id,
            'version' => 1,
            'minimum_monthly_contribution' => '500.00',
            'contribution_due_day' => 25,
            'contribution_grace_days' => 2,
            'contribution_penalty_percent' => '5',
            'loan_interest_percent' => '10',
            'loan_interest_method' => 'flat',
            'loan_interest_basis' => 'per_term',
            'loan_max_term_months' => 6,
            'loan_penalty_percent' => '5',
        ]);

        app(GenerateContributionObligations::class)
            ->forCycle($cycle);

        return $cycle;
    }

    private function createOwnerAndSociety(): array
    {
        $owner = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $society = app(CreateSociety::class)->execute(
            $owner,
            ['name' => 'Test Savings Society']
        );

        $this->seed(ContributionPermissionSeeder::class);

        return [$owner, $society];
    }

    private function paymentData(string $key): array
    {
        return [
            'amount' => '300.00',
            'paid_on' => '2027-03-20',
            'payment_method' => 'cash',
            'payment_reference' => 'TEST-PAYMENT',
            'notes' => 'March contribution',
            'idempotency_key' => $key,
        ];
    }

    public function test_contribution_generates_balanced_ledger_entries(): void
    {
        $this->travelTo(
            \Carbon\Carbon::parse('2027-03-20 12:00:00')
        );

        [$owner, $society] = $this->createOwnerAndSociety();

        $cycle = $this->createActiveCycle($society);

        $obligation = $cycle->contributionObligations()
            ->whereDate('period_start', '2027-03-01')
            ->firstOrFail();

        $this->actingAs($owner)
            ->withSession([
                'active_society_id' => $society->id,
            ])
            ->post(
                route('contributions.store', $obligation->id),
                $this->paymentData((string) Str::uuid())
            )
            ->assertRedirect();

        $this->assertDatabaseHas('contribution_payments', [
            'society_id' => $society->id,
            'cycle_id' => $cycle->id,
            'contribution_obligation_id' => $obligation->id,
            'amount' => '300.00',
        ]);

        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseCount('ledger_lines', 2);

        $transaction = \App\Models\LedgerTransaction::firstOrFail();

        $lines = $transaction->lines()->get();

        $debits = $lines->sum(function ($line) {
            return \App\Support\Money::toCents(
                $line->debit_amount
            );
        });

        $credits = $lines->sum(function ($line) {
            return \App\Support\Money::toCents(
                $line->credit_amount
            );
        });

        $this->assertSame(30000, $debits);
        $this->assertSame(30000, $credits);
    }

    public function test_repeated_request_cannot_duplicate_payment(): void
    {
        $this->travelTo(
            \Carbon\Carbon::parse('2027-03-20 12:00:00')
        );

        [$owner, $society] = $this->createOwnerAndSociety();

        $cycle = $this->createActiveCycle($society);

        $obligation = $cycle->contributionObligations()
            ->whereDate('period_start', '2027-03-01')
            ->firstOrFail();

        $key = (string) Str::uuid();

        $payload = $this->paymentData($key);

        $this->actingAs($owner)
            ->withSession([
                'active_society_id' => $society->id,
            ])
            ->post(
                route('contributions.store', $obligation->id),
                $payload
            )
            ->assertRedirect();

        $this->post(
            route('contributions.store', $obligation->id),
            $payload
        )->assertRedirect();

        $this->assertDatabaseCount('contribution_payments', 1);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseCount('ledger_lines', 2);
    }

    public function test_cross_society_contribution_posting_is_blocked(): void
    {
        $this->travelTo(
            \Carbon\Carbon::parse('2027-03-20 12:00:00')
        );

        [$ownerA, $societyA] = $this->createOwnerAndSociety();
        [$ownerB, $societyB] = $this->createOwnerAndSociety();

        $cycleB = $this->createActiveCycle($societyB);

        $foreignObligation = $cycleB->contributionObligations()
            ->firstOrFail();

        $this->actingAs($ownerA)
            ->withSession([
                'active_society_id' => $societyA->id,
            ])
            ->post(
                route(
                    'contributions.store',
                    $foreignObligation->id
                ),
                $this->paymentData((string) Str::uuid())
            )
            ->assertNotFound();

        $this->assertDatabaseCount('contribution_payments', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_obligation_generation_is_repeatable(): void
    {
        [$owner, $society] = $this->createOwnerAndSociety();

        $cycle = $this->createActiveCycle($society);

        $originalCount = $cycle
            ->contributionObligations()
            ->count();

        app(GenerateContributionObligations::class)
            ->forCycle($cycle);

        $this->assertSame(
            $originalCount,
            $cycle->contributionObligations()->count()
        );

        $this->assertSame(12, $originalCount);
    }
}
