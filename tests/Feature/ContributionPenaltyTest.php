<?php

namespace Tests\Feature;

use App\Models\ContributionPenalty;
use App\Models\Cycle;
use App\Models\Society;
use App\Models\User;
use App\Services\Contributions\GenerateContributionObligations;
use App\Services\Contributions\RecordContribution;
use App\Services\Penalties\AssessContributionPenalty;
use App\Services\Societies\CreateSociety;
use App\Support\Percentage;
use Carbon\Carbon;
use Database\Seeders\PenaltyPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContributionPenaltyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(
            Carbon::parse(
                '2027-03-29 12:00:00',
                'Africa/Maseru'
            )
        );
    }

    private function makeSociety(
        string $name = 'Test Savings Society'
    ): array {
        $owner = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $society = app(CreateSociety::class)->execute(
            $owner,
            ['name' => $name]
        );

        $society->memberships()->update([
            'joined_at' => '2027-02-01 00:00:00',
        ]);

        $this->seed(PenaltyPermissionSeeder::class);

        return [$owner, $society];
    }

    private function makeCycle(Society $society): Cycle
    {
        $cycle = $society->cycles()->create([
            'name' => 'March 2027',
            'code' => 'MAR2027',
            'start_date' => '2027-03-01',
            'end_date' => '2027-03-31',
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
            'contribution_penalty_percent' => '5.00000',
            'loan_interest_percent' => '10.00000',
            'loan_interest_method' => 'flat',
            'loan_interest_basis' => 'per_term',
            'loan_max_term_months' => 6,
            'loan_penalty_percent' => '5.00000',
        ]);

        app(GenerateContributionObligations::class)
            ->forCycle($cycle);

        return $cycle;
    }

    private function contribute(
        Society $society,
        User $owner,
        Cycle $cycle,
        string $amount,
        string $paidOn
    ): void {
        $obligation = $cycle->contributionObligations()
            ->firstOrFail();

        app(RecordContribution::class)->execute(
            $society,
            $owner,
            $obligation->id,
            [
                'amount' => $amount,
                'paid_on' => $paidOn,
                'payment_method' => 'cash',
                'payment_reference' => null,
                'notes' => null,
                'idempotency_key' => (string) Str::uuid(),
            ]
        );
    }

    private function makePenalty(): array
    {
        [$owner, $society] = $this->makeSociety();

        $cycle = $this->makeCycle($society);

        $this->contribute(
            $society,
            $owner,
            $cycle,
            '300.00',
            '2027-03-20'
        );

        $obligation = $cycle->contributionObligations()
            ->firstOrFail();

        $penalty = app(
            AssessContributionPenalty::class
        )->execute($obligation->id);

        $this->assertNotNull($penalty);

        return [$owner, $society, $cycle, $penalty];
    }

    public function test_penalty_is_assessed_on_unpaid_minimum(): void
    {
        [$owner, $society, $cycle, $penalty] =
            $this->makePenalty();

        $this->assertSame(
            '200.00',
            $penalty->base_amount
        );

        $this->assertSame(
            '10.00',
            $penalty->assessed_amount
        );

        $this->assertSame(
            '2027-03-27',
            $penalty->cutoff_date->toDateString()
        );

        $this->assertSame(
            1000,
            $penalty->outstandingCents()
        );

        $this->assertDatabaseCount(
            'contribution_penalties',
            1
        );
    }

    public function test_penalty_assessment_is_repeatable(): void
    {
        [$owner, $society, $cycle, $penalty] =
            $this->makePenalty();

        $obligation = $cycle->contributionObligations()
            ->firstOrFail();

        $again = app(
            AssessContributionPenalty::class
        )->execute($obligation->id);

        $this->assertEquals($penalty->id, $again->id);

        $this->artisan(
            'mokhatlo:assess-contribution-penalties'
        )->assertExitCode(0);

        $this->assertDatabaseCount(
            'contribution_penalties',
            1
        );
    }

    public function test_late_payment_does_not_erase_penalty(): void
    {
        [$owner, $society] = $this->makeSociety();

        $cycle = $this->makeCycle($society);

        $this->contribute(
            $society,
            $owner,
            $cycle,
            '300.00',
            '2027-03-20'
        );

        // The remaining contribution arrived after cutoff.
        $this->contribute(
            $society,
            $owner,
            $cycle,
            '200.00',
            '2027-03-28'
        );

        $obligation = $cycle->contributionObligations()
            ->firstOrFail();

        $penalty = app(
            AssessContributionPenalty::class
        )->execute($obligation->id);

        $this->assertNotNull($penalty);

        // The base remains the amount unpaid at cutoff.
        $this->assertSame(
            '200.00',
            $penalty->base_amount
        );

        $this->assertSame(
            '10.00',
            $penalty->assessed_amount
        );
    }

    public function test_full_payment_before_cutoff_has_no_penalty(): void
    {
        [$owner, $society] = $this->makeSociety();

        $cycle = $this->makeCycle($society);

        $this->contribute(
            $society,
            $owner,
            $cycle,
            '500.00',
            '2027-03-20'
        );

        $obligation = $cycle->contributionObligations()
            ->firstOrFail();

        $result = app(
            AssessContributionPenalty::class
        )->execute($obligation->id);

        $this->assertNull($result);

        $this->assertDatabaseCount(
            'contribution_penalties',
            0
        );
    }

    public function test_penalty_payment_creates_balanced_ledger(): void
    {
        [$owner, $society, $cycle, $penalty] =
            $this->makePenalty();

        $this->actingAs($owner)
            ->withSession([
                'active_society_id' => $society->id,
            ])
            ->post(
                route('penalties.payments.store', $penalty->id),
                [
                    'amount' => '6.00',
                    'paid_on' => '2027-03-29',
                    'payment_method' => 'cash',
                    'payment_reference' => 'PEN-001',
                    'notes' => null,
                    'idempotency_key' => (string) Str::uuid(),
                ]
            )
            ->assertRedirect(
                route('penalties.show', $penalty->id)
            );

        $this->assertDatabaseHas(
            'contribution_penalty_payments',
            [
                'society_id' => $society->id,
                'contribution_penalty_id' => $penalty->id,
                'amount' => '6.00',
            ]
        );

        $ledger = \App\Models\LedgerTransaction::query()
            ->where('source_type', 'contribution_penalty_payment')
            ->firstOrFail();

        $lines = $ledger->lines()->get();

        $debits = $lines->sum(
            fn ($line) => \App\Support\Money::toCents(
                $line->debit_amount
            )
        );

        $credits = $lines->sum(
            fn ($line) => \App\Support\Money::toCents(
                $line->credit_amount
            )
        );

        $this->assertSame(600, $debits);
        $this->assertSame(600, $credits);

        $penalty->refresh();

        $this->assertSame(
            400,
            $penalty->outstandingCents()
        );
    }

    public function test_waiver_reduces_balance_without_creating_cash(): void
    {
        [$owner, $society, $cycle, $penalty] =
            $this->makePenalty();

        $ledgerCountBefore =
            \App\Models\LedgerTransaction::count();

        $this->actingAs($owner)
            ->withSession([
                'active_society_id' => $society->id,
            ])
            ->post(
                route('penalties.waivers.store', $penalty->id),
                [
                    'amount' => '4.00',
                    'reason' =>
                        'Approved correction to the penalty assessment.',
                    'idempotency_key' => (string) Str::uuid(),
                ]
            )
            ->assertRedirect();

        $penalty->refresh();

        $this->assertSame(
            600,
            $penalty->outstandingCents()
        );

        $this->assertSame(
            $ledgerCountBefore,
            \App\Models\LedgerTransaction::count()
        );
    }

    public function test_waiver_cannot_exceed_outstanding_penalty(): void
    {
        [$owner, $society, $cycle, $penalty] =
            $this->makePenalty();

        $this->actingAs($owner)
            ->withSession([
                'active_society_id' => $society->id,
            ])
            ->post(
                route('penalties.waivers.store', $penalty->id),
                [
                    'amount' => '12.00',
                    'reason' =>
                        'Attempt to waive more than the balance.',
                    'idempotency_key' => (string) Str::uuid(),
                ]
            )
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount(
            'contribution_penalty_waivers',
            0
        );
    }

    public function test_outsider_cannot_view_another_society_penalty(): void
    {
        [$owner, $society, $cycle, $penalty] =
            $this->makePenalty();

        [$outsider, $otherSociety] =
            $this->makeSociety('Other Society');

        $this->actingAs($outsider)
            ->withSession([
                'active_society_id' => $otherSociety->id,
            ])
            ->get(route('penalties.show', $penalty->id))
            ->assertNotFound();
    }

    public function test_percentage_calculation_rounds_correctly(): void
    {
        $this->assertSame(
            1000,
            Percentage::applyToCents(
                20000,
                '5.00000'
            )
        );

        $this->assertSame(
            101,
            Percentage::applyToCents(
                10000,
                '1.00500'
            )
        );

        $this->assertSame(
            0,
            Percentage::applyToCents(
                10000,
                '0.00000'
            )
        );
    }
}
