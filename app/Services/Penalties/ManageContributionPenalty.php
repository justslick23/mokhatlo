<?php

namespace App\Services\Penalties;

use App\Models\ContributionPenalty;
use App\Models\ContributionPenaltyPayment;
use App\Models\ContributionPenaltyWaiver;
use App\Models\Society;
use App\Models\User;
use App\Services\Accounting\PostPenaltyPaymentToLedger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageContributionPenalty
{
    public function __construct(
        private readonly PostPenaltyPaymentToLedger $ledger
    ) {
    }

    public function recordPayment(
        Society $society,
        User $actor,
        int $penaltyId,
        array $data
    ): ContributionPenaltyPayment {
        return DB::transaction(function () use (
            $society,
            $actor,
            $penaltyId,
            $data
        ) {
            Society::query()
                ->whereKey($society->id)
                ->lockForUpdate()
                ->firstOrFail();

            $penalty = ContributionPenalty::query()
                ->where('society_id', $society->id)
                ->whereKey($penaltyId)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = ContributionPenaltyPayment::query()
                ->where('society_id', $society->id)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing) {
                $sameRequest =
                    $existing->contribution_penalty_id === $penalty->id
                    && Money::toCents($existing->amount)
                        === Money::toCents($data['amount'])
                    && $existing->paid_on->toDateString()
                        === $data['paid_on']
                    && $existing->payment_method
                        === $data['payment_method']
                    && $existing->payment_reference
                        === ($data['payment_reference'] ?? null)
                    && $existing->notes
                        === ($data['notes'] ?? null);

                if (! $sameRequest) {
                    throw ValidationException::withMessages([
                        'payment' =>
                            'This payment request has already been used.',
                    ]);
                }

                return $existing;
            }

            $penalty->load(['cycle', 'payments', 'waivers']);

            if ($penalty->cycle->status !== 'active') {
                throw ValidationException::withMessages([
                    'cycle' =>
                        'Penalty payments are currently restricted to active cycles.',
                ]);
            }

            if (
                $data['paid_on']
                < $penalty->assessed_on->toDateString()
            ) {
                throw ValidationException::withMessages([
                    'paid_on' =>
                        'The payment date cannot precede penalty assessment.',
                ]);
            }

            $amountCents = Money::toCents($data['amount']);

            if (
                $amountCents <= 0
                || $amountCents > $penalty->outstandingCents()
            ) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'The amount exceeds the outstanding penalty.',
                ]);
            }

            $payment = ContributionPenaltyPayment::create([
                'society_id' => $society->id,
                'contribution_penalty_id' => $penalty->id,
                'amount' => Money::fromCents($amountCents),
                'paid_on' => $data['paid_on'],
                'payment_method' => $data['payment_method'],
                'payment_reference' =>
                    $data['payment_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
                'recorded_by' => $actor->id,
            ]);

            // Payment and accounting entries commit together.
            $this->ledger->execute($payment);

            return $payment;
        }, 3);
    }

    public function waive(
        Society $society,
        User $actor,
        int $penaltyId,
        array $data
    ): ContributionPenaltyWaiver {
        return DB::transaction(function () use (
            $society,
            $actor,
            $penaltyId,
            $data
        ) {
            Society::query()
                ->whereKey($society->id)
                ->lockForUpdate()
                ->firstOrFail();

            $penalty = ContributionPenalty::query()
                ->where('society_id', $society->id)
                ->whereKey($penaltyId)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = ContributionPenaltyWaiver::query()
                ->where('society_id', $society->id)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing) {
                if (
                    $existing->contribution_penalty_id !== $penalty->id
                    || Money::toCents($existing->amount)
                        !== Money::toCents($data['amount'])
                    || $existing->reason !== $data['reason']
                ) {
                    throw ValidationException::withMessages([
                        'waiver' =>
                            'This waiver request has already been used.',
                    ]);
                }

                return $existing;
            }

            $penalty->load(['cycle', 'payments', 'waivers']);

            if ($penalty->cycle->status !== 'active') {
                throw ValidationException::withMessages([
                    'cycle' =>
                        'Penalty waivers require an active cycle.',
                ]);
            }

            $amountCents = Money::toCents($data['amount']);

            if (
                $amountCents <= 0
                || $amountCents > $penalty->outstandingCents()
            ) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'The waiver exceeds the outstanding penalty.',
                ]);
            }

            return ContributionPenaltyWaiver::create([
                'society_id' => $society->id,
                'contribution_penalty_id' => $penalty->id,
                'amount' => Money::fromCents($amountCents),
                'reason' => $data['reason'],
                'idempotency_key' => $data['idempotency_key'],
                'waived_by' => $actor->id,
            ]);
        }, 3);
    }
}
