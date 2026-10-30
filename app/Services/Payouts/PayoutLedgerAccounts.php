<?php

namespace App\Services\Payouts;

use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\Society;
use App\Support\Money;
use DomainException;

final class PayoutLedgerAccounts
{
    public function find(
        Society $society,
        string $key,
        string $expectedType
    ): LedgerAccount {
        $code = config("mokhatlo_payouts.accounts.{$key}");

        if (! is_string($code) || $code === '') {
            throw new DomainException(
                "Payout ledger account {$key} is not configured."
            );
        }

        $account = LedgerAccount::query()
            ->where('society_id', $society->id)
            ->where('code', $code)
            ->first();

        if (
            ! $account
            || $account->type !== $expectedType
            || ! $account->is_active
        ) {
            throw new DomainException(
                "Missing or invalid payout account {$code}. " .
                'Reconcile the existing ledger configuration first.'
            );
        }

        return $account;
    }

    public function penaltyPayable(Society $society): LedgerAccount
    {
        $code = config(
            'mokhatlo_payouts.accounts.penalties_payable'
        );

        $account = LedgerAccount::firstOrCreate(
            [
                'society_id' => $society->id,
                'code' => $code,
            ],
            [
                'name' => 'Member Penalty Distributions Payable',
                'type' => 'liability',
                'is_active' => true,
            ]
        );

        if (
            $account->type !== 'liability'
            || ! $account->is_active
        ) {
            throw new DomainException(
                'The member penalty payable account is invalid.'
            );
        }

        return $account;
    }

    /*
     * Net credit recorded for one cycle and source type
     * against a specific GL account.
     */
    public function sourceNetCredit(
        Society $society,
        int $cycleId,
        string $sourceType,
        LedgerAccount $account
    ): int {
        $total = 0;

        LedgerTransaction::query()
            ->where('society_id', $society->id)
            ->where('cycle_id', $cycleId)
            ->where('source_type', $sourceType)
            ->with('lines')
            ->orderBy('id')
            ->chunkById(200, function ($transactions) use (
                $account,
                &$total
            ) {
                foreach ($transactions as $transaction) {
                    foreach ($transaction->lines as $line) {
                        if (
                            (int) $line->ledger_account_id
                            !== (int) $account->id
                        ) {
                            continue;
                        }

                        $total +=
                            Money::toCents($line->credit_amount)
                            - Money::toCents($line->debit_amount);
                    }
                }
            });

        return $total;
    }

    public function assertCreditAtLeast(
        LedgerAccount $account,
        int $requiredCents
    ): void {
        $creditBalance = 0;

        $account->lines()
            ->orderBy('id')
            ->chunkById(200, function ($lines) use (
                &$creditBalance
            ) {
                foreach ($lines as $line) {
                    $creditBalance +=
                        Money::toCents($line->credit_amount)
                        - Money::toCents($line->debit_amount);
                }
            });

        if ($creditBalance < $requiredCents) {
            throw new DomainException(
                "Account {$account->code} has insufficient " .
                'recorded credit balance for this payout.'
            );
        }
    }
}
