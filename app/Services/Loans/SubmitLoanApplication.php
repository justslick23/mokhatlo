<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\Society;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SubmitLoanApplication
{
    public function __construct(
        private readonly QuoteFlatLoan $calculator
    ) {}

    private function canCaptureForMembers(
        User $actor,
        Society $society
    ): bool {
        $previousTeamId = getPermissionsTeamId();

        try {
            setPermissionsTeamId($society->id);

            $actor->unsetRelation('roles');
            $actor->unsetRelation('permissions');

            return $actor->can('loans.create_for_members');
        } finally {
            setPermissionsTeamId($previousTeamId);

            $actor->unsetRelation('roles');
            $actor->unsetRelation('permissions');
        }
    }

    public function execute(
        Society $society,
        User $actor,
        array $data
    ): Loan {
        if (
            empty($data['request_key'])
            || ! Str::isUuid((string) $data['request_key'])
        ) {
            throw ValidationException::withMessages([
                'request_key' => 'A valid request key is required.',
            ]);
        }

        return DB::transaction(function () use (
            $society,
            $actor,
            $data
        ) {
            $lockedSociety = Society::query()
                ->whereKey($society->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSociety->status !== 'active') {
                throw ValidationException::withMessages([
                    'society' => 'This society is not active.',
                ]);
            }

            $actorMembership = $society->memberships()
                ->where('user_id', $actor->id)
                ->where('status', 'active')
                ->first();

            if (! $actorMembership) {
                abort(403, 'You are not an active society member.');
            }

            /*
             * Resolve borrower:
             * - Missing membership ID: the actor.
             * - Different membership ID: capture on behalf.
             */
            $targetId = $data['society_membership_id'] ?? null;

            $isForAnotherMember = $targetId !== null
                && (int) $targetId !== (int) $actorMembership->id;

            if ($isForAnotherMember) {
                abort_unless(
                    $this->canCaptureForMembers($actor, $society),
                    403,
                    'You cannot capture loans for other members.'
                );
            }

            $borrower = $targetId !== null
                ? $society->memberships()
                    ->where('status', 'active')
                    ->findOrFail($targetId)
                : $actorMembership;

            /*
             * Check request key before generating another loan.
             * Locking society serialises same-society submissions.
             */
            $existing = $society->loans()
                ->where('request_key', $data['request_key'])
                ->first();

            if ($existing) {
                $sameRequest =
                    (int) $existing->society_membership_id
                        === (int) $borrower->id

                    && (int) $existing->submitted_by
                        === (int) $actor->id

                    && Money::toCents($existing->principal_amount)
                        === Money::toCents(
                            (string) $data['principal_amount']
                        )

                    && $existing->purpose
                        === trim($data['purpose']);

                if (! $sameRequest) {
                    throw ValidationException::withMessages([
                        'request_key' =>
                            'This request key belongs to another loan.',
                    ]);
                }

                return $existing;
            }

            $cycle = $society->cycles()
                ->where('status', 'active')
                ->with('policy')
                ->lockForUpdate()
                ->first();

            if (! $cycle || ! $cycle->policy) {
                throw ValidationException::withMessages([
                    'cycle' => 'An active savings cycle is required.',
                ]);
            }

            $policy = $cycle->policy;

            if ($policy->loan_interest_method !== 'flat') {
                throw ValidationException::withMessages([
                    'loan' => 'Only flat-interest loans are supported.',
                ]);
            }

            $termMonths = (int) $policy->loan_maturity_months;
            $maximumTerm = (int) $policy->loan_max_term_months;

            if (
                $termMonths < 1
                || $termMonths > 60
                || $termMonths > $maximumTerm
            ) {
                throw ValidationException::withMessages([
                    'loan' =>
                        'The configured maturity period exceeds the allowed term.',
                ]);
            }

            $quote = $this->calculator->calculate(
                (string) $data['principal_amount'],
                (string) $policy->loan_interest_percent,
                (string) $policy->loan_interest_basis,
                $termMonths
            );

            /*
             * Business rule:
             * Borrower owns any refundable interest
             * actually collected against this loan.
             */
            $loan = $society->loans()->create([
                'cycle_id' => $cycle->id,
                'society_membership_id' => $borrower->id,

                // Legacy column retained, but no separate lender.
                'lender_membership_id' => null,

                'submitted_by' => $actor->id,
                'principal_amount' => $quote['principal_amount'],
                'purpose' => trim($data['purpose']),
                'term_months' => $termMonths,

                'interest_rate_percent' =>
                    $quote['interest_rate_percent'],

                'interest_method' => 'flat',
                'interest_basis' => $quote['interest_basis'],
                'policy_version' => $policy->version,

                'loan_penalty_rate_percent' =>
                    (string) $policy->loan_penalty_percent,

                'quoted_interest_amount' =>
                    $quote['interest_amount'],

                'quoted_total_amount' =>
                    $quote['total_amount'],

                'request_key' => $data['request_key'],

                // Verbal approval already occurred.
                'status' => 'approved',
                'submitted_at' => now(),

                // No separate approval workflow.
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => null,
            ]);

            $loan->update([
                'loan_number' => 'LN-' . str_pad(
                    (string) $loan->id,
                    8,
                    '0',
                    STR_PAD_LEFT
                ),
            ]);

            $loan->events()->create([
                'society_id' => $society->id,
                'actor_id' => $actor->id,
                'event_type' => 'captured',
                'from_status' => null,
                'to_status' => 'approved',
                'notes' => $isForAnotherMember
                    ? 'Loan captured on behalf of member following verbal agreement.'
                    : 'Member loan captured following verbal agreement.',
            ]);

            return $loan;
        }, 3);
    }
}
