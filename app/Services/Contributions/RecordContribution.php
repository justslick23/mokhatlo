<?php

namespace App\Services\Contributions;

use App\Models\ContributionObligation;
use App\Models\ContributionPayment;
use App\Models\Society;
use App\Models\User;
use App\Services\Accounting\PostContributionToLedger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordContribution
{
    public function __construct(
        private readonly PostContributionToLedger $ledger
    ) {
    }

    public function execute(
        Society $society,
        User $actor,
        int $obligationId,
        array $data
    ): ContributionPayment {
        return DB::transaction(function () use (
            $society,
            $actor,
            $obligationId,
            $data
        ) {
            Society::query()
                ->whereKey($society->id)
                ->lockForUpdate()
                ->firstOrFail();

            $obligation = ContributionObligation::query()
                ->where('society_id', $society->id)
                ->whereKey($obligationId)
                ->lockForUpdate()
                ->firstOrFail();

            $obligation->load(['cycle', 'membership']);

            $existing = ContributionPayment::query()
                ->where('society_id', $society->id)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing) {
                $sameRequest =
                    $existing->contribution_obligation_id === $obligation->id
                    && Money::toCents($existing->amount)
                        === Money::toCents($data['amount'])
                    && $existing->payment_method === $data['payment_method']
                    && $existing->paid_on->toDateString() === $data['paid_on']
                    && $existing->payment_reference
                        === ($data['payment_reference'] ?? null)
                    && $existing->notes === ($data['notes'] ?? null);

                if (! $sameRequest) {
                    throw ValidationException::withMessages([
                        'payment' => 'This payment request identifier has already been used.',
                    ]);
                }

                return $existing;
            }

            if ($obligation->cycle->status !== 'active') {
                throw ValidationException::withMessages([
                    'cycle' => 'The savings cycle is not active.',
                ]);
            }

            if (
                $obligation->membership->status !== 'active'
                || $obligation->membership->society_id !== $society->id
                || $obligation->cycle->society_id !== $society->id
            ) {
                throw ValidationException::withMessages([
                    'member' => 'This member is not active in the selected society.',
                ]);
            }

            if (
                $data['paid_on']
                < $obligation->cycle->start_date->toDateString()
            ) {
                throw ValidationException::withMessages([
                    'paid_on' => 'The payment date cannot precede the cycle.',
                ]);
            }

            $amountCents = Money::toCents($data['amount']);

            if ($amountCents <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'The amount must be greater than zero.',
                ]);
            }

            $payment = ContributionPayment::create([
                'society_id' => $society->id,
                'cycle_id' => $obligation->cycle_id,
                'society_membership_id' =>
                    $obligation->society_membership_id,
                'contribution_obligation_id' => $obligation->id,
                'amount' => Money::fromCents($amountCents),
                'paid_on' => $data['paid_on'],
                'payment_method' => $data['payment_method'],
                'payment_reference' =>
                    $data['payment_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
                'recorded_by' => $actor->id,
            ]);

            $this->ledger->execute($payment);

            return $payment;
        }, 3);
    }
}
