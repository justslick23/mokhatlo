<?php

namespace App\Services\Accounting;

use App\Models\LedgerAccount;
use App\Models\Society;
use App\Support\Money;
use DomainException;

final class SocietyAssetAccounts
{
    private const METHODS = [
        'cash' => ['CASH_ON_HAND', 'Cash on Hand'],
        'bank_transfer' => ['BANK_ACCOUNT', 'Bank Account'],
        'mobile_money' => ['MOBILE_MONEY', 'Mobile Money'],
    ];

    public function forMethod(
        Society $society,
        string $method
    ): LedgerAccount {
        if (! isset(self::METHODS[$method])) {
            throw new DomainException(
                'Unsupported payment method.'
            );
        }

        [$code, $name] = self::METHODS[$method];

        $account = LedgerAccount::firstOrCreate(
            [
                'society_id' => $society->id,
                'code' => $code,
            ],
            [
                'name' => $name,
                'type' => 'asset',
                'is_active' => true,
            ]
        );

        if ($account->type !== 'asset' || ! $account->is_active) {
            throw new DomainException(
                'The selected financial account is unavailable.'
            );
        }

        return $account;
    }

    public function balanceCents(LedgerAccount $account): int
    {
        if ($account->type !== 'asset') {
            throw new DomainException(
                'Account must be an asset account.'
            );
        }

        $balance = 0;

        // Read exact decimal strings rather than relying
        // on floating-point aggregate calculations.
        $account->lines()
            ->orderBy('id')
            ->chunkById(500, function ($lines) use (&$balance) {
                foreach ($lines as $line) {
                    $balance += Money::toCents(
                        $line->debit_amount
                    );

                    $balance -= Money::toCents(
                        $line->credit_amount
                    );
                }
            });

        return $balance;
    }
}
